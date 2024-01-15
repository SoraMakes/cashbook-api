<?php

namespace App\Console\Commands;

use App\Services\ExportService;
use Illuminate\Console\Command;


class ExportDataCommand extends Command {
    protected $signature = 'export:data {--exportDocuments} {--convertToJpeg}';
    protected $description = 'Export data to CSV and optionally images.';

    public function handle() {
        $exportService = new ExportService();
        $exportDocuments = $this->option('exportDocuments');
        $convertToJpeg = $this->option('convertToJpeg');

        $exportPath = $exportService->exportData($exportDocuments, $convertToJpeg);

        $this->info($exportPath);

        return $exportPath;
    }
}
