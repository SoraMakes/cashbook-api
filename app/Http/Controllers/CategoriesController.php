<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CategoriesController extends Controller {
    public function index() {
        return response()->json(Category::all());
    }

    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $category = Category::create(array_merge(
            $request->all(),
            ['user_id' => Auth::id()]
        ));

        return response()->json($category, 201);
    }

    public function update($id, Request $request) {
        throw new Exception('Not implemented');
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name,' . $category->id
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // create copy of original category
        $history_entry = Entry::create(array_merge($category->toArray(), ['id' => null]));
        // and delete it (keeping it as history)
        $history_entry->delete();
        Log::debug('Created and soft deleted history entry', ['id' => $history_entry->id]);

        $category->update(array_merge(
            $request->all()),
            ['user_last_modified_id' => Auth::id(), 'category_id' => $category->id]
        );

        return response()->json($category, 200);

    }

    public function delete($id) {
        throw new Exception('Not implemented');
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(null, 204);
    }
}
