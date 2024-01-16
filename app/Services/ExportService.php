<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Intervention\Image\ImageManager;

class ExportService {
    public function exportData(bool $exportDocuments, bool $convertToJpeg): string {
        // create exports folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/exports')) {
            Log::debug('Creating exports folder');
            mkdir(storage_path() . '/app/exports');
        }

        // Create a CSV Writer instance
        $csvPath = storage_path('app/exports/temp.csv');
        $csv = Writer::createFromPath($csvPath, 'w+');

        // Add CSV headers
        $csv->insertOne([
            'Entry ID', 'Category Name', 'Amount', 'Recipient/Sender',
            'Payment Method', 'Description', 'No Invoice', 'Date',
            'Created At', 'Username Created', 'Updated At', 'Username Updated',
            'Attached Document Count'
        ]);

        // Fetch entries with related models
        $entries = Entry::with(['category', 'user', 'user_last_modified', 'documents'])->get();

        foreach ($entries as $entry) {
            // Format data and add to CSV
            $csv->insertOne($this->formatEntryForCsv($entry));

            // Handle document export if required
            if ($exportDocuments) {
                $this->exportDocuments($entry, $convertToJpeg);
            }
        }

        // Create ZIP file
        $zipPath = storage_path('app/exports/' . $this->generateZipFilename($exportDocuments, $convertToJpeg));
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Add CSV to ZIP
        $zip->addFile($csvPath, 'export.csv');

        // Add documents to ZIP if exported
        if ($exportDocuments) {
            // Add exported documents to the ZIP
            $this->addDocumentsToZip($zip);
        }

        $zip->close();

        // Clean up temporary CSV and documents
        unlink($csvPath);
        if ($exportDocuments) {
            Storage::deleteDirectory('exports/documents');
        }

        // Cleanup old exports
        $this->cleanupExports();

        // Return ZIP path
        return $zipPath;
    }

    private function generateZipFilename($exportDocuments, $convertToJpeg): string {
        $block_date = date('Y-m-d');
        $block_documents = "";
        if ($exportDocuments) {
            if ($convertToJpeg) {
                $block_documents = "documents_jpeg_";
            } else {
                $block_documents = "documents_";
            }
        }

        return $block_date . '_export_' . $block_documents . time() . '.zip';
    }

    private function cleanupExports(): void {
        $exportsPath = storage_path('app/exports');
        $allExports = glob($exportsPath . '/*.zip');

        // Sort files by creation time
        usort($allExports, function ($a, $b) {
            return filectime($b) - filectime($a);
        });

        // Get limit values from environment, or use defaults
        $maxFiles = env('EXPORT_MAX_FILES', 10);
        $maxDays = env('EXPORT_MAX_DAYS', 7);

        // Keep files of the last $maxDays days, maximum $maxFiles files
        foreach ($allExports as $index => $exportFile) {
            if ($index >= $maxFiles || filectime($exportFile) < strtotime("-{$maxDays} days")) {
                unlink($exportFile);
            }
        }
    }

    private function formatEntryForCsv($entry): array {
        return [
            $entry->id,
            $entry->category ? $entry->category->name : '',
            $entry->is_income ? $entry->amount : -$entry->amount,
            $entry->recipient_sender,
            $entry->payment_method,
            $entry->description,
            $entry->no_invoice ? 'Yes' : 'No',
            $entry->date,
            $entry->created_at->toDateTimeString(),
            $entry->user ? $entry->user->username : '',
            $entry->updated_at->toDateTimeString(),
            $entry->user_last_modified ? $entry->user_last_modified->username : '',
            $entry->documents->count()
        ];
    }

    private function exportDocuments($entry, $convertToJpeg): void
    {
        foreach ($entry->documents as $document) {
            $originalPath = storage_path('app/' . $document->original_path);
            if (file_exists($originalPath)) {
                $destinationPath = storage_path('app/exports/documents/' . $entry->id);
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                if ($convertToJpeg && $this->isUncommonImageFormat($originalPath)) {
                    // Change file extension of $destinationFile to .jpg
                    $jpegFilename = pathinfo($document->original_filename, PATHINFO_FILENAME) . '.jpg';
                    $destinationFile = $destinationPath . '/' . $document->id . '_' . $jpegFilename;


                    // Convert image to JPEG
                    $img = ImageManager::imagick()->read($originalPath);
                    $img->toJpeg(80)->save($destinationFile);
                } else {
                    $destinationFile = $destinationPath . '/' . $document->id . '_' . $document->original_filename;

                    // Copy original file
                    copy($originalPath, $destinationFile);
                }
            } else {
                Log::error('Document ' . $document->id . ' for entry ' . $entry->id . ' does not exist at ' . $originalPath . '!');
            }
        }
    }

    private function isUncommonImageFormat($filePath): bool
    {
        $imageExtensions = ['bmp', 'svg', 'webp', 'avif'];
        return in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), $imageExtensions);
    }

    private function addDocumentsToZip($zip): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(storage_path('app/exports/documents'))
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(storage_path('app/exports/')));

                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}
