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
        if (!$this->isRunningAsCorrectUser()) {
            $this->error('Error: This command must not be run as root or any other unintended user.');
            return 1;
        }

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

    private function isRunningAsCorrectUser(): bool {
        if (env('APP_USER') == '') {
            $this->warn('APP_USER is not set. Skipping user check.');
            return true;
        }
        return posix_getpwuid(posix_geteuid())['name'] === env('APP_USER');
    }
}
