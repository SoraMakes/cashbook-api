<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Writer;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use Intervention\Image\ImageManager;

class ExportService {
    public function exportData(bool $exportDocuments, bool $convertToJpeg, string $exportFormat = 'zip'): string {
        // create exports folder if it doesn't exist
        if (!file_exists(storage_path() . '/app/exports')) {
            Log::debug('Creating exports folder');
            mkdir(storage_path() . '/app/exports');
        }

        // Create a CSV Writer instance
        $csvPath = storage_path('app/exports/temp.csv');
        $csv = Writer::createFromPath($csvPath, 'w+');
        // Set UTF-8 encoding with BOM
        $csv->setOutputBOM(Writer::BOM_UTF8);
        // Set delimiter to comma or semicolon based on your needs
        $csv->setDelimiter(';');

        // Add CSV headers
        $csv->insertOne([
            'Date',
            'Recipient/Sender',
            'Description',
            'Amount',
            'Category Name',
            'is income',
            'Payment Method',
            'No Invoice',
            'Attached Document Count',
            'Entry ID',
            'Created At',
            'Username Created',
            'Updated At',
            'Username Updated',
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

        // Determine the export file path based on the format
        $exportFilePath = storage_path('app/exports/' . $this->generateExportFilename($exportDocuments, $convertToJpeg, $exportFormat));

        if ($exportFormat == 'zip') {
            $this->createZip($exportFilePath, $csvPath, $exportDocuments);
        } elseif ($exportFormat == 'tar.gz') {
            $this->createTarGz($exportFilePath, $csvPath, $exportDocuments);
        }

        // Clean up temporary CSV and documents
        unlink($csvPath);
        if ($exportDocuments) {
            Storage::deleteDirectory('exports/documents');
        }

        // Cleanup old exports
        $this->cleanupExports();

        // Return ZIP path
        return $exportFilePath;
    }

    private function formatEntryForCsv($entry): array {
        return [
            $entry->date,
            $entry->recipient_sender,
            $entry->description,
            $entry->amount == null ? ($entry->is_income ? $entry->amount : -$entry->amount) : '',
            $entry->category ? $entry->category->name : '',
            $entry->is_income,
            $entry->payment_method,
            $entry->no_invoice ? 'Yes' : 'No',
            $entry->documents->count(),
            $entry->id,
            $entry->created_at->toDateTimeString(),
            $entry->user ? $entry->user->username : '',
            $entry->updated_at->toDateTimeString(),
            $entry->user_last_modified ? $entry->user_last_modified->username : '',
        ];
    }


    private function generateExportFilename($exportDocuments, $convertToJpeg, $exportFormat): string {
        $block_date = date('Y-m-d');
        $block_documents = "";
        if ($exportDocuments) {
            if ($convertToJpeg) {
                $block_documents = "documents_jpeg_";
            } else {
                $block_documents = "documents_";
            }
        }
        return $block_date . '_export_' . $block_documents . time() . '.' . ($exportFormat == 'tar.gz' ? 'tar.gz' : 'zip');
    }

    private function createZip($zipPath, $csvPath, $exportDocuments) {
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        // Add CSV to ZIP
        $zip->addFile($csvPath, 'export.csv');

        // Add documents to ZIP if exported
        if ($exportDocuments) {
            $this->addDocumentsToZip($zip);
        }

        $zip->close();
    }

    private function createTarGz($tarGzPath, $csvPath, $exportDocuments) {
        $tar = new PharData(str_replace('.gz', '', $tarGzPath));
        $tar->addFile($csvPath, 'export.csv');

        // Add documents to TAR.GZ if exported
        if ($exportDocuments) {
            $this->addDocumentsToTarGz($tar);
        }

        $tar->compress(Phar::GZ);
        unset($tar);
        // Remove TAR file after compressing it to TAR.GZ
        unlink(str_replace('.gz', '', $tarGzPath));
    }

    private function addDocumentsToTarGz($tar) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(storage_path('app/exports/documents'))
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(storage_path('app/exports/')));

                $tar->addFile($filePath, $relativePath);
            }
        }

    }

    private function cleanupExports(): void {
        Log::debug('Cleaning up exports');
        $exportsPath = storage_path('app/exports');
        $allExports = glob($exportsPath . '/*');

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
                Log::debug('Deleting export ' . $exportFile);
                unlink($exportFile);
            }
        }
    }

    private function exportDocuments($entry, $convertToJpeg): void {
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

    private function isUncommonImageFormat($filePath): bool {
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
