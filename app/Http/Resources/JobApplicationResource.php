<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\JobApplication */
class JobApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'job_post' => $this->whenLoaded('jobPost', fn() => [
                'id' => $this->jobPost->id,
                'title' => $this->jobPost->title,
            ]),
            'applicant' => $this->whenLoaded('jobSeeker', fn() => [
                'id' => $this->jobSeeker->id,
                'name' => $this->jobSeeker->name,
                'email' => $this->jobSeeker->email,
            ]),
        ];
    }
}