<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmployerProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role !== UserRole::Employer) {
            return response()->json([
                'message' => 'Only employers can access this profile.',
            ], 403);
        }

        $profile = $user->employerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'Employer profile not found.',
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

        if ($user->role !== UserRole::Employer) {
            return response()->json([
                'message' => 'Only employers can create this profile.',
            ], 403);
        }

        if ($user->employerProfile) {
            return response()->json([
                'message' => 'Employer profile already exists.',
            ], 409);
        }

        $validated = $request->validate([
            'employer_type' => 'required|in:individual,company,organization',
            'organization_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
        ]);

        $profile = $user->employerProfile()->create($validated);

        return response()->json([
            'message' => 'Employer profile created successfully.',
            'profile' => $profile,
        ], 201);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->role !== UserRole::Employer) {
            return response()->json([
                'message' => 'Only employers can update this profile.',
            ], 403);
        }

        $profile = $user->employerProfile;

        if (!$profile) {
            return response()->json([
                'message' => 'Employer profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'employer_type' => 'sometimes|required|in:individual,company,organization',
            'organization_name' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|nullable|string',
            'logo' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|url|max:255',
        ]);

        $profile->update($validated);

        return response()->json([
            'message' => 'Employer profile updated successfully.',
            'profile' => $profile,
        ]);
    }
}
