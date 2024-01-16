<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExportController;
use App\Services\ExportService;
use Illuminate\Console\Command;


class ExportDataCommand extends Command {
    protected $signature = 'export:data {--exportDocuments} {--convertToJpeg} {--returnDownloadLink}';
    protected $description = 'Export data to CSV and optionally images.';

    public function handle() {
        $exportService = new ExportService();
        $exportDocuments = $this->option('exportDocuments');
        $convertToJpeg = $this->option('convertToJpeg');
        $returnDownloadLink = $this->option('returnDownloadLink');

        $exportPath = $exportService->exportData($exportDocuments, $convertToJpeg);

        if ($returnDownloadLink) {
            $downloadParameters = ExportController::createTemporaryDownloadParameter(basename($exportPath));
            $url = '/api/export/download?' . $downloadParameters;
            $this->info($url);
            return $url;
        } else {
            $this->info($exportPath);
            return $exportPath;
        }
    }
}
