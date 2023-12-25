<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EntriesController extends Controller {
    public function index() {
        // Retrieve all non-deleted entries
        return response()->json(Entry::all());
    }

    public function show($id) {
        // Retrieve a single entry
        $entry = Entry::findOrFail($id);

        return response()->json($entry, 200);
    }

    /**
     * @throws Exception
     */
    public function store(Request $request) {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|numeric',
            'recipient_sender' => 'required|string|max:255',
            'payment_method' => 'required|in:cash,bank_transfer,not_payed',
            'description' => 'required|string',
            'no_invoice' => 'required|boolean',
            'date' => 'required|date',
            'document' => 'sometimes',
        ]);

        $validator->sometimes('document', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return !is_array($input->document);
        });

        $validator->sometimes('document.*', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return is_array($input->document);
        });


        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        DB::beginTransaction();
        // process entry
        try {
            $entry = Entry::create(array_merge(
                $request->all(),
                ['user_id' => Auth::id()]
            ));
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong while creating the entry'], 400);
        }

        // process documents
        if ($request->has('document') && !empty($request->document)) {
            try {
                DocumentController::processOneOrMultipleFiles($request->document, $entry->id);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        DB::commit();


        return response()->json($entry, 201);
    }

    /**
     * @throws Exception
     */
    public function update($id, Request $request) {
        # todo: adjust document references to new entry

        $request['id'] = $id; // add id to request so it can be validated
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:entries,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'amount' => 'sometimes|numeric',
            'recipient_sender' => 'sometimes|string|max:255',
            'payment_method' => 'sometimes|in:cash,bank_transfer,not_payed',
            'description' => 'sometimes|string',
            'no_invoice' => 'sometimes|boolean',
            'date' => 'sometimes|date',
            'document' => 'sometimes',
        ]);

        $validator->sometimes('document', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return !is_array($input->document);
        });

        $validator->sometimes('document.*', 'file|mimes:avif,webp,jpg,jpeg,png,pdf|max:4096', function ($input) {
            return is_array($input->document);
        });

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }


        try {
            $originalEntry = Entry::findOrFail($id);
        } catch (Exception $e) {
            // Check if the entry exists, including soft-deleted entries
            $originalEntry = Entry::withTrashed()->where('id', $id)->first();

            // Check if entry is not found or if it's soft-deleted
            if (!$originalEntry) {
                return response()->json(['error' => 'Entry not found'], 404);
            } elseif ($originalEntry->trashed()) {
                return response()->json(['error' => 'Entry is deleted'], 410); // 410 Gone status code
            }
        }

        DB::beginTransaction();

        // Create a new entry with the updated data
        try {
            // create copy of previous
            $oldEntry = clone $originalEntry;

            $originalEntry->update([
                'entry_id' => $oldEntry->id,
                'category_id' => $request->input('category_id', $originalEntry->category_id),
                'amount' => $request->input('amount', $originalEntry->amount),
                'recipient_sender' => $request->input('recipient_sender', $originalEntry->recipient_sender),
                'payment_method' => $request->input('payment_method', $originalEntry->payment_method),
                'description' => $request->input('description', $originalEntry->description),
                'no_invoice' => $request->input('no_invoice', $originalEntry->no_invoice),
                'date' => $request->input('date', $originalEntry->date),
            ]);

            // Soft delete the original entry
            $oldEntry->delete();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong while updating the entry'], 400);
        }

        // process documents
        if ($request->has('document') && !empty($request->document)) {
            try {
                DocumentController::processOneOrMultipleFiles($request->document, $originalEntry->id);
            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        DB::commit();


        return response()->json($originalEntry, 200);
    }

    public function delete($id) {
        $entry = Entry::findOrFail($id);
        $entry->delete();

        return response()->json(null, 204);
    }
}
