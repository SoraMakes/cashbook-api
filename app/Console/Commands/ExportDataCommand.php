<?php

namespace App\Console\Commands;

use App\Http\Controllers\ExportController;
use App\Services\ExportService;
use Illuminate\Console\Command;


class ExportDataCommand extends Command {
    protected $signature = 'export:data
                            {--exportDocuments}
                            {--convertToJpeg}
                            {--returnDownloadLink : Return a temporary download link instead of the file path}
                            {--exportFormat=zip : The format of the export file (zip or tar.gz)}';
    protected $description = 'Export data to CSV and optionally images.';

    public function handle() {
        $exportService = new ExportService();
        $exportDocuments = $this->option('exportDocuments');
        $convertToJpeg = $this->option('convertToJpeg');
        $returnDownloadLink = $this->option('returnDownloadLink');
        $exportFormat = $this->option('exportFormat');

        $exportPath = $exportService->exportData($exportDocuments, $convertToJpeg, $exportFormat);

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
