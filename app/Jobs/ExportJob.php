<?php

namespace App\Jobs;

use App\Services\ExportService;
use Illuminate\Support\Facades\Log;

class ExportJob extends Job {
    protected $exportDocuments;
    protected $convertToJpeg;

    public function __construct($exportDocuments, $convertToJpeg) {
        $this->exportDocuments = $exportDocuments;
        $this->convertToJpeg = $convertToJpeg;
    }

    public function handle() {
        // Implement the logic to perform the export
        Log::info('Export Job running', [
            'exportDocuments' => $this->exportDocuments,
            'convertToJpeg' => $this->convertToJpeg
        ]);

        $exportService = new ExportService();

        $exportPath = $exportService->exportData($this->exportDocuments, $this->convertToJpeg);

        Log::info('Export Job finished', [
            'exportPath' => $exportPath
        ]);
    }
}
