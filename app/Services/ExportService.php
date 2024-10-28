<?php

namespace App\Services;

use App\Models\Entry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\CharsetConverter;
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
            Storage::createDirectory('/app/exports');
        }

        // create temporary folder
        if (file_exists(storage_path('app/tmp/export'))) {
            Log::debug('Deleting old temporary folder');
            Storage::deleteDirectory('tmp/export');
        }
        Log::debug('Creating temporary folder');
        Storage::createDirectory('tmp/export/documents');

        // Create a CSV Writer instance
        $csvPath = storage_path('app/tmp/export') . '/temp.csv';
        $csv = Writer::createFromPath($csvPath, 'w+');
        // Custom formatter for boolean values
        $csv->addFormatter(function (array $row) {
            return array_map(function ($value) {
                if (is_bool($value)) {
                    return $value ? 'Yes' : 'No';
                }
                return $value;
            }, $row);
        });
        // Export in ANSI format to avoid Excel issues with german special characters
        $encoder = (new CharsetConverter())
            ->inputEncoding('utf-8')
            ->outputEncoding('iso-8859-15');
        $csv->addFormatter($encoder);
        // Set delimiter to comma or semicolon based on your needs
        $csv->setDelimiter(';');

        // Add CSV headers
        $csv->insertOne([
            'Entry ID',
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

        // ensure folder storage_path('app/exports/') exists
        if (!file_exists(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'));
        }

        // Determine the export file path based on the format
        $exportFilePath = storage_path('app/exports/') . $this->generateExportFilename($exportDocuments, $convertToJpeg, $exportFormat);

        if ($exportFormat == 'zip') {
            $this->createZip($exportFilePath, $csvPath, $exportDocuments);
        } elseif ($exportFormat == 'tar.gz') {
            $this->createTarGz($exportFilePath, $csvPath, $exportDocuments);
        }

        // Clean up temporary CSV and documents
        Storage::deleteDirectory('tmp/export');

        // Cleanup old exports
        $this->cleanupExports();

        // Return ZIP path
        return $exportFilePath;
    }

    private function formatEntryForCsv($entry): array {
        return [
            $entry->id,
            $entry->date,
            $entry->recipient_sender,
            $entry->description,
            $entry->amount == null ? '' : ($entry->is_income ? $entry->amount : -$entry->amount),
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
            $this->addDocumentsToArchive($zip);
        }

        $zip->close();
    }

    private function createTarGz($tarGzPath, $csvPath, $exportDocuments) {
        // Create a temporary .tar file path in the /tmp directory
        $tempTarPath = '/tmp/' . basename($tarGzPath, '.gz');

        $tar = new PharData($tempTarPath);
        $tar->addFile($csvPath, 'export.csv');

        // Add documents to TAR.GZ if exported
        if ($exportDocuments) {
            $this->addDocumentsToArchive($tar);
        }

        // Compress .tar to .tar.gz
        $tar->compress(Phar::GZ);

        // Move the compressed .tar.gz file to the intended path
        rename($tempTarPath . '.gz', $tarGzPath);

        // Clean up: Remove the temporary .tar file
        unlink($tempTarPath);
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
        $entryFolderName = $entry->id . '_' . $entry->category->name . '_' . $entry->recipient_sender . '_' . $entry->description;
        $entryFolderName = preg_replace('/[^A-Za-z0-9äöü_()+ß,.\-]/', '_', $entryFolderName);
        $entryFolderName = substr($entryFolderName, 0, 100);

        foreach ($entry->documents as $document) {
            $originalPath = storage_path('app/') . $document->original_path;
            if (file_exists($originalPath)) {
                $destinationPathAbsolute = storage_path('app/tmp/export/documents/' . $entryFolderName);
                $destinationPathForStorageClass = 'tmp/export/documents/' . $entryFolderName;
                if (!file_exists($destinationPathAbsolute)) {
                    Storage::createDirectory($destinationPathForStorageClass);
                }

                if ($convertToJpeg && $this->isUncommonImageFormat($originalPath)) {
                    // PHP Tar only allows max 100 chars long filenames https://stackoverflow.com/a/24801016
                    $shortenedOriginalFilename = substr(pathinfo($document->original_filename, PATHINFO_FILENAME), 0, 92);
                    // Change file extension of $destinationFile to .jpg
                    $jpegFilename = $shortenedOriginalFilename . '.jpg';
                    $destinationFile = $destinationPathAbsolute . '/' . $document->id . '_' . $jpegFilename;


                    // Convert image to JPEG
                    $img = ImageManager::imagick()->read($originalPath);
                    $img->toJpeg(80)->save($destinationFile);
                } else {
                    // PHP Tar only allows max 100 chars long filenames https://stackoverflow.com/a/24801016
                    $shortenedOriginalFilename = substr(pathinfo($document->original_filename, PATHINFO_FILENAME), 0, 92) . "." . pathinfo($document->original_filename, PATHINFO_EXTENSION);
                    $destinationFile = $destinationPathAbsolute . '/' . $document->id . '_' . $shortenedOriginalFilename;

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

    /**
     * @param $tar
     * @return void
     */
    private function addDocumentsToArchive($archive): void {
        if (!file_exists(storage_path('app/tmp/export/documents'))) {
            Log::info('Documents folder does not exist, skipping adding documents to archive');
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(storage_path('app/tmp/export/documents'))
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(storage_path('app/tmp/export/')));

                $archive->addFile($filePath, $relativePath);
            }
        }
    }
}
