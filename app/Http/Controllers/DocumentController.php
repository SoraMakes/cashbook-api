<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Imagick;
use Intervention\Image\ImageManager;
use Spatie\PdfToImage\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller {
    /**
     * @throws Exception
     */
    public function store(Request $request, $entryId) {
        $request['entry_id'] = $entryId; // add entry_id to request so it can be validated
        $validator = Validator::make($request->all(), [
            'document' => 'required',
            'entry_id' => 'required|exists:entries,id', // entry must exist in the database
        ]);

        $validator->sometimes('document', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return !is_array($input->document);
        });

        $validator->sometimes('document.*', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return is_array($input->document);
        });


        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        DB::beginTransaction();
        try {
            $documents = self::processOneOrMultipleFiles($request->document, $entryId);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        DB::commit();


        return response()->json($documents, 201);
    }

    /**
     * @throws Exception
     */
    public static function processOneOrMultipleFiles($files, $entryId): array {
        $documents = [];
        try {
            if (is_array($files)) {
                foreach ($files as $file) {
                    $documents[] = self::processFile($file, $entryId); // process each file
                }
            } else {
                $documents[] = self::processFile($files, $entryId); // process single file
            }
        } catch (Exception $e) {
            // Delete any files that were saved before the error occurred
            foreach ($documents as $document) {
                Storage::delete($document['file_path']);
                Storage::delete($document['thumbnail_path']);
            }

            // throw the exception noticing that something went wrong while processing the files
            throw new Exception('Something went wrong while processing the files');
        }
        return $documents;
    }

    private static function processFile($file, $entryId): \Illuminate\Http\JsonResponse|Document {
        $originalFilename = $file->getClientOriginalName();
        $filename = md5(time() . $file->getClientOriginalName()) . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('documents', $filename);
        $thumbnailPath = null;


        // create thumbnails folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/thumbnails')) {
            mkdir(storage_path() . '/app/thumbnails');
        }

        if (self::isPDF($file)) {
            $pdf = new Pdf($file);
            $pdf->setPage(1);
            // save to temparary file
            $tmp_file = tempnam(sys_get_temp_dir(), 'jpg');
            $pdf->saveImage($tmp_file);
            $thumbnail = ImageManager::imagick()->read($tmp_file);
            unlink($tmp_file);
        } elseif (self::isImage($file)) {
            $thumbnail = ImageManager::imagick()->read($file);
        } else {
            return response()->json(['message' => 'File type not supported'], 400);
        }

        // Create and store thumbnail for images
        // if original file was pdf: it is image now
        if ($thumbnail->width() > $thumbnail->height())
            $thumbnail->scaleDown(null, 128);
        else
            $thumbnail->scaleDown(128);

        $extension = 'webp'; // Define the extension
        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        $thumbnailFilename = $filenameWithoutExtension . '.' . $extension;
        $thumbnailPath = 'thumbnails/' . $thumbnailFilename;
        $thumbnail->toWebp(50)->save(storage_path() . '/app/' . $thumbnailPath);

        $document = new Document([
            'entry_id' => $entryId,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'thumbnail_path' => $thumbnailPath,
        ]);

        $document->save();

        return $document;
    }


    public function index($entryId) {
        $documents = Document::where('entry_id', $entryId)->get();
        return response()->json($documents);
    }

    public function thumbnail($documentId) {
        $document = Document::findOrFail($documentId);

        if ($document->thumbnail_path) {
            $path = storage_path('app/' . $document->thumbnail_path);

            if (file_exists($path)) {
                $file = file_get_contents($path);
                $type = mime_content_type($path);  // TODO: save mime type in db

                $headers = [
                    'Content-Type' => $type,
                ];

                return response()->download($path, $document->original_filename, $headers);
//                $response = new BinaryFileResponse($path, 200 , $headers);
//                return Response::make($file, 200)->header("Content-Type", $type);
            }
        }

        return response('Thumbnail not found', 404);
    }

    public function show($documentId) {
        $document = Document::findOrFail($documentId);

        $path = storage_path('app/' . $document->file_path);
        if (file_exists($path)) {
            $file = file_get_contents($path);
            $type = mime_content_type($path);

            return Response::make($file, 200)->header("Content-Type", $type);
        }

        return response('Document not found', 404);
    }

    public function destroy($documentId) {
        $document = Document::findOrFail($documentId);
        $document->delete();

        return response()->json(['message' => 'Document deleted'], 200);
    }

    private static function isImage($file) {
        return in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg']);
    }

    private static function isPDF($file) {
        return strtolower($file->getClientOriginalExtension()) === 'pdf';
    }
}
