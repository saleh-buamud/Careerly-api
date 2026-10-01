<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobCategory;
use Illuminate\Http\Request;

class JobCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = JobCategory::all();

        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:job_categories,name',
            'description' => 'nullable|string',
        ]);

        $category = JobCategory::create($validated);

        return response()->json([
            'message' => 'Job category created successfully',
            'category' => $category,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = JobCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Job category not found'
            ], 404);
        }

        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = JobCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Job category not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:job_categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json([
            'message' => 'Job category updated successfully',
            'category' => $category,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = JobCategory::find($id);

        if (!$category) {
            return response()->json([
                'message' => 'Job category not found'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'message' => 'Job category deleted successfully'
        ]);
    }
}