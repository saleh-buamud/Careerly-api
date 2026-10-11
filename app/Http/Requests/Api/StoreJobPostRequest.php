<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
        ];
    }
}