<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Imagick;
use Intervention\Image\ImageManager;
use Spatie\PdfToImage\Pdf;

class DocumentController extends Controller
{
    public function store(Request $request, $entryId)
    {
        $request['entry_id'] = $entryId; // add entry_id to request so it can be validated
        $validator = Validator::make($request->all(), [
            'document' => 'required|file|max:10240', // max 10MB
            'entry_id' => 'required|exists:entries,id', // entry must exist in the database
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $file = $request->file('document');
        $originalFilename = $file->getClientOriginalName();
        $filename = md5(time() . $file->getClientOriginalName()) . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('documents', $filename);
        $thumbnailPath = null;


        // create thumbnails folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/thumbnails')) {
            mkdir(storage_path() . '/app/thumbnails');
        }

        if ($this->isPDF($file)) {
            $pdf = new Pdf($file);
            $pdf->setPage(1);
            // save to temparary file
            $tmp_file = tempnam(sys_get_temp_dir(), 'jpg');
            $pdf->saveImage($tmp_file);
            $thumbnail = ImageManager::imagick()->read($tmp_file);
            unlink($tmp_file);
        } elseif ($this->isImage($file)) {
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

        $extension = 'avif'; // Define the extension
        $filenameWithoutExtension = pathinfo($filename, PATHINFO_FILENAME);
        $thumbnailFilename = $filenameWithoutExtension . '.' . $extension;
        $thumbnailPath = 'thumbnails/' . $thumbnailFilename;
        $thumbnail->toAvif(50)->save(storage_path() . '/app/' . $thumbnailPath);

        $document = new Document([
            'entry_id' => $entryId,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            // 'thumbnail_path' => [path to thumbnail, if applicable]
        ]);

        $document->save();

        return response()->json($document, 201);
    }

    public function index($entryId)
    {
        $documents = Document::where('entry_id', $entryId)->get();
        return response()->json($documents);
    }

    public function thumbnail($documentId)
    {
        $document = Document::findOrFail($documentId);

        if ($document->thumbnail_path) {
            $path = storage_path('app/' . $document->thumbnail_path);

            if (file_exists($path)) {
                $file = file_get_contents($path);
                $type = mime_content_type($path);

                return Response::make($file, 200)->header("Content-Type", $type);
            }
        }

        return response('Thumbnail not found', 404);
    }

    public function show($documentId)
    {
        $document = Document::findOrFail($documentId);

        $path = storage_path('app/' . $document->file_path);
        if (file_exists($path)) {
            $file = file_get_contents($path);
            $type = mime_content_type($path);

            return Response::make($file, 200)->header("Content-Type", $type);
        }

        return response('Document not found', 404);
    }

    public function destroy($documentId)
    {
        $document = Document::findOrFail($documentId);
        $document->delete();

        return response()->json(['message' => 'Document deleted'], 200);
    }

    private function isImage($file)
    {
        return in_array($file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg']);
    }

    private function isPDF($file)
    {
        return strtolower($file->getClientOriginalExtension()) === 'pdf';
    }

    private function createPDFThumbnail($filePath, $filename)
    {
        // maybe use spatie/pdf-to-image and then image intervention to create thumbnail
        $imagick = new Imagick();
        $imagick->readImage(storage_path('app/' . $filePath . '[0]'));
        $imagick->setImageFormat('jpg');
        $imagick->thumbnailImage(200, 0);

        $thumbnailPath = 'thumbnails/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $imagick->writeImage(storage_path('app/' . $thumbnailPath));
        $imagick->clear();
        $imagick->destroy();

        return $thumbnailPath;
    }
}
