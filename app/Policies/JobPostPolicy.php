<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JobPostPolicy
{
    public function update(User $user, JobPost $jobPost): Response
    {
        return $this->owns($user, $jobPost)
            ? Response::allow()
            : Response::deny('Unauthorized');
    }

    public function delete(User $user, JobPost $jobPost): Response
    {
        return $this->owns($user, $jobPost)
            ? Response::allow()
            : Response::deny('Unauthorized');
    }

    private function owns(User $user, JobPost $jobPost): bool
    {
        return $user->role === UserRole::Employer
            && $jobPost->employer_id === $user->id;
    }
}