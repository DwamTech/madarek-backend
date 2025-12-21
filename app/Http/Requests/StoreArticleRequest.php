<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'section_id' => $this->route('section'),
            'issue_id' => $this->route('issue'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section_id' => 'required|exists:sections,id',
            'issue_id' => 'required|exists:issues,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:articles,slug|max:255',
            'excerpt' => 'nullable|string',
            'content' => 'required|string',
            'author_name' => 'required|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'gregorian_date' => 'nullable|string',
            'hijri_date' => 'nullable|string',
            'references' => 'nullable|string',
            'status' => ['required', Rule::in([
                env('ARTICLE_STATUS_DRAFT', 'draft'),
                env('ARTICLE_STATUS_PUBLISHED', 'published'),
                env('ARTICLE_STATUS_ARCHIVED', 'archived'),
            ])],
            'published_at' => 'nullable|date',
        ];
    }
}
