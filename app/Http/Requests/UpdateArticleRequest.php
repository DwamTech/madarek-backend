<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
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
            'section_id' => 'sometimes|exists:sections,id',
            'issue_id' => 'sometimes|exists:issues,id',
            'title' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($this->route('article'))],
            'excerpt' => 'nullable|string',
            'content' => 'sometimes|string',
            'author_name' => 'sometimes|string|max:255',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'gregorian_date' => 'nullable|string',
            'hijri_date' => 'nullable|string',
            'references' => 'nullable|string',
            'status' => ['sometimes', Rule::in([
                env('ARTICLE_STATUS_DRAFT', 'draft'),
                env('ARTICLE_STATUS_PUBLISHED', 'published'),
                env('ARTICLE_STATUS_ARCHIVED', 'archived'),
            ])],
            'published_at' => 'nullable|date',
        ];
    }
}
