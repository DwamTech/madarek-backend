<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            // 'user_id' => 'required|integer|exists:users,id',
            'issue_number' => 'nullable|integer|unique:issues,issue_number',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096', // 4MB Max
            'cover_image_alt' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'pdf_file' => 'nullable|file|mimes:pdf',
            // Consider file validation if handling upload
            'hijri_date' => 'nullable|string',
            'gregorian_date' => 'nullable|string',
            'status' => ['nullable', Rule::in([
                env('ISSUE_STATUS_DRAFT', 'draft'),
                env('ISSUE_STATUS_PUBLISHED', 'published'),
                env('ISSUE_STATUS_ARCHIVED', 'archived'),
            ])],
            'published_at' => 'nullable|date',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
