<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CategoryThemas;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('id') ?? $this->input('id');
        
        return [
            'id' => 'required|integer|exists:category_themas,id',
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($categoryId) {
                    $exists = CategoryThemas::whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($value))])
                        ->where('type', $this->input('type', 'website'))
                        ->where('id', '!=', $categoryId)
                        ->exists();
                    if ($exists) {
                        $fail('The ' . $attribute . ' has already been taken for this type.');
                    }
                },
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_themas', 'slug')->ignore($categoryId)
            ],
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
            'id.required' => 'Category ID is required.',
            'id.exists' => 'Category not found.',
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
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active')
            ]);
        }
        
        if ($this->has('sort_order')) {
            $this->merge([
                'sort_order' => $this->integer('sort_order', 0)
            ]);
        }
    }
}