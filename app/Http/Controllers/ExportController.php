<?php

namespace App\Http\Controllers;

use App\Jobs\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;

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
}
