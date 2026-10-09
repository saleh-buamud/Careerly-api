<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobSeekerProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role !== UserRole::JobSeeker) {
            return response()->json([
                'message' => 'Only job seekers can access this profile.',
            ], 403);
        }

        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'Job seeker profile not found.',
            ], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'profile' => $profile,
        ]);
    }
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role !== UserRole::JobSeeker) {
            return response()->json([
                'message' => 'Only job seekers can create this profile.',
            ], 403);
        }

        if ($user->jobSeekerProfile) {
            return response()->json([
                'message' => 'Job seeker profile already exists.',
            ], 409);
        }

        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'headline' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'profile_image' => 'nullable|string|max:255',
        ]);

        $profile = $user->jobSeekerProfile()->create($validated);

        return response()->json([
            'message' => 'Job seeker profile created successfully.',
            'profile' => $profile,
        ], 201);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== UserRole::JobSeeker) {
            return response()->json([
                'message' => 'Only job seekers can update this profile.',
            ], 403);
        }

        $profile = $user->jobSeekerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'Job seeker profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'full_name' => 'sometimes|nullable|string|max:255',
            'headline' => 'sometimes|nullable|string|max:255',
            'bio' => 'sometimes|nullable|string',
            'city' => 'sometimes|nullable|string|max:255',
            'profile_image' => 'sometimes|nullable|string|max:255',
        ]);

        $profile->update($validated);

        return response()->json([
            'message' => 'Job seeker profile updated successfully.',
            'profile' => $profile,
        ]);
    }
}
