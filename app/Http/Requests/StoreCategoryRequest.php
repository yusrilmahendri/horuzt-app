<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CategoryThemas;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $exists = CategoryThemas::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($value))])
                        ->where('type', $this->input('type', 'website'))
                        ->exists();
                    if ($exists) {
                        $fail('The ' . $attribute . ' has already been taken for this type.');
                    }
                },
            ],
            'slug' => 'required|string|max:255|unique:category_themas,slug',
            'type' => 'required|in:website,video',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'slug.required' => 'Category slug is required.',
            'slug.unique' => 'This slug is already in use.',
            'type.required' => 'Category type is required.',
            'type.in' => 'Category type must be either website or video.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->integer('sort_order', 0)
        ]);
    }
}