<?php

namespace App\Http\Controllers;

use App\Jobs\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller {
    public function createExport(Request $request) {
        // validate request
        $validator = Validator::make($request->all(), [
            'export_documents' => 'sometimes|boolean',
            'convert_to_jpeg' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $exportDocuments = $request->input('export_documents', false);
        $convertToJpeg = $request->input('convert_to_jpeg', false);

        // Dispatch export job
        Log::info('Dispatching export job', [
            'export_documents' => $exportDocuments,
            'convert_to_jpeg' => $convertToJpeg
        ]);
        Queue::push(new ExportJob(
            $exportDocuments,
            $convertToJpeg
        ));
        Log::debug('Export job dispatched');

        return response()->json(['message' => 'Export started']);
    }

    public function index(Request $request) {
        $files = Storage::disk('local')->files('exports');
        $exports = [];

        foreach ($files as $file) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}_export_(documents_jpeg_|documents_)?\d+\.(zip|tar\.gz)$/', basename($file))) {
                $export = $this->parseFileInfo($file);
                $export['download_parameter'] = self::createTemporaryDownloadParameter($export['filename']);
                $exports[] = $export;
            }
        }

        return response()->json($exports);
    }

    public static function createTemporaryDownloadParameter($filename): string {
        // workaround for lumen not being able to generate signed URLs
        $expiresAt = Carbon::now()->addHours(6)->timestamp;
        $signature = hash_hmac('sha256', $filename . $expiresAt, env('APP_KEY'));

        return "name=" . urlencode($filename) . "&expires=" . $expiresAt . "&signature=" . $signature;
    }

    private function parseFileInfo($file) {
        $filename = basename($file);
        $filesize = Storage::disk('local')->size($file);
        $timestamp = Storage::disk('local')->lastModified($file);
        $containsDocuments = str_contains($filename, 'documents_');
        $convertedToJpeg = str_contains($filename, 'documents_jpeg_');
        $archiveFormat = str_contains($filename, '.zip') ? 'zip' : 'tar.gz';

        return [
            'filename' => $filename,
            'filesize' => $filesize,
            'created_timestamp' => date('Y-m-d H:i:s', $timestamp),
            'contains_documents' => $containsDocuments,
            'images_converted_to_jpeg' => $convertedToJpeg,
            'archive_format' => $archiveFormat
        ];
    }

    public function downloadExport(Request $request) {
        $name = $request->input('name');
        $expires = $request->input('expires');
        $signature = $request->input('signature');

        Log::info('Downloading export', ['name' => $name]);

        if ($this->isValidToken($name, $expires, $signature)) {
            $filePath = Storage::disk('local')->path('exports/' . $name);

            return response()->stream(function () use ($filePath) {
                $stream = fopen($filePath, 'r');
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                "Content-Type" => "application/zip",
                "Content-Disposition" => "attachment; filename=\"" . basename($filePath) . "\""
            ]);
        } else {
            return response()->json(['error' => 'Invalid or expired link'], 401);
        }
    }

    private function isValidToken($filename, $expires, $signature) {
        $expected = hash_hmac('sha256', $filename . $expires, env('APP_KEY'));
        if ($expected === $signature && Carbon::now()->timestamp <= $expires) {
            return true;
        }
        return false;
    }
}
