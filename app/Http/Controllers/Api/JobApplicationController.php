<?php

namespace App\Http\Controllers\Api;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Models\JobPost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    public function store(Request $request, string $jobPost): \Illuminate\Http\JsonResponse
    {
        $post = JobPost::publiclyVisible()->find($jobPost);

        if (!$post) {
            return response()->json(['message' => 'Job post not found.'], 404);
        }

        $jobSeekerId = $request->user()->id;

        if (
            JobApplication::where('job_post_id', $post->id)
                ->where('job_seeker_id', $jobSeekerId)
                ->exists()
        ) {
            return response()->json(['message' => 'You have already applied to this job.'], 409);
        }

        try {
            $application = JobApplication::create([
                'job_post_id' => $post->id,
                'job_seeker_id' => $jobSeekerId,
                'status' => JobApplicationStatus::Pending,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'You have already applied to this job.'], 409);
        }

        return response()->json([
            'message' => 'Application submitted successfully.',
            'application' => (new JobApplicationResource($application))->resolve(),
        ], 201);
    }

    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $filters = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $applications = JobApplication::query()
            ->whereHas('jobPost', fn($query) => $query->where('employer_id', $request->user()->id))
            ->with([
                'jobPost:id,title',
                'jobSeeker:id,name,email',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return JobApplicationResource::collection($applications);
    }

    public function update(Request $request, int $jobApplication): \Illuminate\Http\JsonResponse
    {
        $application = JobApplication::query()
            ->whereHas('jobPost', fn($query) => $query->where('employer_id', $request->user()->id))
            ->find($jobApplication);

        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,reviewed,accepted,rejected',
        ]);
        $nextStatus = JobApplicationStatus::from($validated['status']);

        if (!$application->status->canTransitionTo($nextStatus)) {
            return response()->json(['message' => 'This application status transition is not allowed.'], 409);
        }

        $application->update(['status' => $nextStatus]);

        return response()->json([
            'message' => 'Application status updated successfully.',
            'application' => (new JobApplicationResource($application))->resolve(),
        ]);
    }
}