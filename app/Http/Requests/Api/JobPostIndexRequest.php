<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class JobPostIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:job_categories,id',
            'location' => 'sometimes|string|max:255',
            'employment_type' => 'sometimes|in:full_time,part_time,remote,freelance,internship',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}