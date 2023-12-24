<?php

namespace App\Http\Controllers;

use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EntriesController extends Controller
{
    public function index()
    {
        // Retrieve all non-deleted entries
        return response()->json(Entry::all());
    }

    public function show($id)
    {
        // Retrieve a single entry
        $entry = Entry::findOrFail($id);

        return response()->json($entry, 200);
    }

    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|integer|exists:categories,id',
            'amount' => 'required|numeric',
            'recipient_sender' => 'required|string|max:255',
            'payment_method' => 'required|in:cash,bank_transfer,not_payed',
            'description' => 'required|string',
            'no_invoice' => 'required|boolean',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        $entry = Entry::create(array_merge(
            $request->all(),
            ['user_id' => Auth::id()]
        ));

        return response()->json($entry, 201);
    }

    public function update($id, Request $request)
    {
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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }


        try {
            $originalEntry = Entry::findOrFail($id);
        } catch (\Exception $e) {
            // Check if the entry exists, including soft-deleted entries
            $originalEntry = Entry::withTrashed()->where('id', $id)->first();

            // Check if entry is not found or if it's soft-deleted
            if (!$originalEntry) {
                return response()->json(['error' => 'Entry not found'], 404);
            } elseif ($originalEntry->trashed()) {
                return response()->json(['error' => 'Entry is deleted'], 410); // 410 Gone status code
            }
        }

        $updatedData = [
            'category_id' => $request->input('category_id', $originalEntry->category_id),
            'amount' => $request->input('amount', $originalEntry->amount),
            'recipient_sender' => $request->input('recipient_sender', $originalEntry->recipient_sender),
            'payment_method' => $request->input('payment_method', $originalEntry->payment_method),
            'description' => $request->input('description', $originalEntry->description),
            'no_invoice' => $request->input('no_invoice', $originalEntry->no_invoice),
            'date' => $request->input('date', $originalEntry->date),
        ];

        // Create a new entry with the updated data
        $newEntry = $originalEntry->replicateWithHistory($updatedData, Auth::id());
        $newEntry->save();

        // Soft delete the original entry
        $originalEntry->delete();

        return response()->json($newEntry, 200);
    }

    public function delete($id)
    {
        $entry = Entry::findOrFail($id);
        $entry->delete();

        return response()->json(null, 204);
    }
}
