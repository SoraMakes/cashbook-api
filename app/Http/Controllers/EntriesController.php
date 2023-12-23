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
        $originalEntry = Entry::findOrFail($id);
        $updatedData = $request->all();

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
