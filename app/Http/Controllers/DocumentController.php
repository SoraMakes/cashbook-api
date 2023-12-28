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
                    $documents = array_merge($documents, self::processFile($file, $entryId)); // process each file
                }
            } else {
                $documents = array_merge($documents, self::processFile($files, $entryId)); // process single file
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

    private static function processFile($file, $entryId) {
        // create thumbnails folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/thumbnails')) {
            mkdir(storage_path() . '/app/thumbnails');
        }
        // create thumbnails folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/originals')) {
            mkdir(storage_path() . '/app/originals');
        }
        // create thumbnails folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/documents')) {
            mkdir(storage_path() . '/app/documents');
        }


        $filename = $file->getClientOriginalName();
        $filename_hash = md5(time() . $file->getClientOriginalName()) . '.' . $file->getClientOriginalExtension();
        $originalPath = $file->storeAs('originals', $filename_hash);


        if (self::isPDF($file)) {
            $pdf = new Pdf($file);

            // PDFs can have multiple pages, storing every page as a separate image
            for ($i = 1; $i <= $pdf->getNumberOfPages(); $i++) {
                $pdf->setPage($i);
                // save to temparary file
                $tmp_file = tempnam(sys_get_temp_dir(), 'jpg');
                $pdf->saveImage($tmp_file);
                $inputImage[] = ImageManager::imagick()->read($tmp_file);
                unlink($tmp_file);
            }
        } elseif (self::isImage($file)) {
            $inputImage[] = ImageManager::imagick()->read($file);
        } else {
            return response()->json(['message' => 'File type not supported'], 400);
        }

        $extension = 'webp'; // Define the extension
        $filenameWithoutExtension = pathinfo($filename_hash, PATHINFO_FILENAME);
        $convertImageFilename = $filenameWithoutExtension . '.' . $extension;

        $savedDocuments = [];

        foreach ($inputImage as $page) {
            $thumbnail = clone $page;
            $document = $page;
            // Create and store thumbnail for images; if original file was pdf: it is image now
            if ($document->width() > $document->height()) {
                $document->scaleDown(null, 1920);
                $thumbnail->scaleDown(null, 100);
            } else {
                $document->scaleDown(1920);
                $thumbnail->scaleDown(100);
            }

            $thumbnailPath = 'thumbnails/' . $convertImageFilename;
            $thumbnail->toWebp(50)->save(storage_path() . '/app/' . $thumbnailPath);

            $documentPath = 'documents/' . $convertImageFilename;
            $document->toWebp(50)->save(storage_path() . '/app/' . $documentPath);



            $document = Document::create([
                'entry_id' => $entryId,
                'original_path' => $originalPath,
                'document_path' => $documentPath,
                'original_filename' => $filename,
                'thumbnail_path' => $thumbnailPath,
            ]);

            $savedDocuments[] = $document;
        }


        return $savedDocuments;
    }


    public function index($entryId) {
        $documents = Document::where('entry_id', $entryId)->get();
        return response()->json($documents);
    }

    public function thumbnail($documentId) {
        return $this->load_and_return_file($documentId, 'thumbnail');
    }

    private function load_and_return_file($documentId, $type) {
        $document = Document::findOrFail($documentId);

        if ($document->thumbnail_path) {
            switch ($type) {
                case 'thumbnail':
                    $path = storage_path('app/' . $document->thumbnail_path);
                    break;
                case 'document':
                    $path = storage_path('app/' . $document->document_path);
                    break;
                case 'original':
                    $path = storage_path('app/' . $document->original_path);
                    break;
                default:
                    return response('File not found', 404);
            }

            if (file_exists($path)) {
                $type = mime_content_type($path);

                return response()->download($path, $document->original_filename, ['Content-Type' => $type]);
            }
        }

        return response('Thumbnail not found', 404);
    }

    public function show($documentId) {
        return $this->load_and_return_file($documentId, 'document');
    }

    public function original($documentId) {
        return $this->load_and_return_file($documentId, 'original');
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
