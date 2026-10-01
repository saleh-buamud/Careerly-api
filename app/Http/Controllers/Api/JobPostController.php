<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobPost;

class JobPostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = JobPost::all();
        return response()->json($posts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated
            = $request->validate([
                'category_id' => 'required|exists:job_categories,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',

                'requirements' => 'nullable|string',
                'location' => 'nullable|string|max:255',

                'employment_type' => 'nullable|in:full_time,part_time,remote,freelance,internship',

                'salary_min' => 'nullable|integer|min:0',
                'salary_max' => 'nullable|integer|min:0',
                'salary_currency' => 'nullable|string|max:10',

                'contact_email' => 'nullable|email|required_without_all:contact_phone,google_form_url',
                'contact_phone' => 'nullable|string',
                'google_form_url' => 'nullable|url',

                'status' => 'required|in:active,closed',
                'expires_at' => 'nullable|date',
            ]);
        $employerId = auth()->id();

        $jobPost = JobPost::create([
            'employer_id' => $employerId,
            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],

            'requirements' => $validated['requirements'] ?? null,
            'location' => $validated['location'] ?? null,
            'employment_type' => $validated['employment_type'] ?? null,

            'salary_min' => $validated['salary_min'] ?? null,
            'salary_max' => $validated['salary_max'] ?? null,
            'salary_currency' => $validated['salary_currency'] ?? null,

            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'google_form_url' => $validated['google_form_url'] ?? null,

            'status' => $validated['status'],
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        return response()->json([
            'message' => 'Job post created successfully',
            'job_post' => $jobPost,
        ], 201);


    }

    /**
     * Display the specified resource.
     */
    public function show(string $jobPost)
    {
        $post = JobPost::find($jobPost);

        if (!$post) {
            return response()->json(['message' => 'Job post not found'], 404);
        }

        return response()->json($post);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $jobPost)
    {
        $post = JobPost::find($jobPost);

        if (!$post) {
            return response()->json(['message' => 'Job post not found'], 404);
        }
        if ($post->employer_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $validated
            = $request->validate([
                'category_id' => 'required|exists:job_categories,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',

                'requirements' => 'nullable|string',
                'location' => 'nullable|string|max:255',

                'employment_type' => 'nullable|in:full_time,part_time,remote,freelance,internship',

                'salary_min' => 'nullable|integer|min:0',
                'salary_max' => 'nullable|integer|min:0',
                'salary_currency' => 'nullable|string|max:10',

                'contact_email' => 'nullable|email|required_without_all:contact_phone,google_form_url',
                'contact_phone' => 'nullable|string',
                'google_form_url' => 'nullable|url',

                'status' => 'required|in:active,closed',
                'expires_at' => 'nullable|date',
            ]);

        $post->update($validated);

        return response()->json([
            'message' => 'Job post updated successfully',
            'job_post' => $post,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $jobPost)
    {
        $post = JobPost::find($jobPost);

        if (!$post) {
            return response()->json(['message' => 'Job post not found'], 404);
        }
        if ($post->employer_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        $post->delete();

        return response()->json(['message' => 'Job post deleted']);
    }
}
