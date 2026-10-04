<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\JobPost */
class JobPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'category_id' => $this->category_id,

            'title' => $this->title,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'location' => $this->location,
            'employment_type' => $this->employment_type,

            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,

            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'google_form_url' => $this->google_form_url,

            'status' => $this->status,
            'expires_at' => $this->expires_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name,
                ];
            }),

            'employer' => $this->whenLoaded('employer', function () {
                return [
                    'id' => $this->employer?->id,
                    'name' => $this->employer?->name,
                ];
            }),

            'skills' => $this->whenLoaded('skills', function () {
                return $this->skills->map(function ($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                    ];
                });
            }),
        ];
    }
}
