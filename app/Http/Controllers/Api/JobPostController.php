<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobPostResource;
use Illuminate\Http\Request;
use App\Models\JobPost;
use App\Models\JobCategory;

class JobPostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:job_categories,id',
            'location' => 'sometimes|string|max:255',
            'employment_type' => 'sometimes|in:full_time,part_time,remote,freelance,internship',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = JobPost::publiclyVisible()
            ->with(['category', 'employer', 'skills']);

        if (!empty($filters['q'])) {
            $keyword = '%' . $filters['q'] . '%';
            $query->where(function ($query) use ($keyword): void {
                $query->where('title', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['location'])) {
            $query->where('location', 'like', '%' . $filters['location'] . '%');
        }

        if (isset($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        $posts = $query
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->appends($filters);

        return JobPostResource::collection($posts);
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
                'skills' => 'nullable|array',
                'skills.*' => 'exists:skills,id',
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
        $jobPost->skills()->sync($validated['skills'] ?? []);


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
        $post = JobPost::publiclyVisible()
            ->with(['category', 'employer'])
            ->find($jobPost);

        if (!$post) {
            return response()->json([
                'message' => 'Job post not found'
            ], 404);
        }

        return new JobPostResource($post);
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
                'skills' => 'nullable|array',
                'skills.*' => 'exists:skills,id',
            ]);

        $post->update($validated);

        $syncResult = $post->skills()->sync($validated['skills'] ?? []);

        return new JobPostResource($post->load(['category', 'employer', 'skills']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $jobPost)
    {
        $post = JobPost::find($jobPost);

        if (!$post) {
            return response()->json([
                'message' => 'Job post not found'
            ], 404);
        }

        if ($post->employer_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $post->skills()->detach();

        $post->delete();

        return response()->json([
            'message' => 'Job post deleted'
        ]);
    }
}
