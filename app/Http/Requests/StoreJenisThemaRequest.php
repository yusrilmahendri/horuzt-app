<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJenisThemaRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|integer|exists:category_themas,id',
            'name' => 'required|string|max:255|unique:jenis_themas,name',
            'price' => 'required|numeric|min:0',
            'preview' => 'required|string|max:500',
            'preview_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'url_thema' => 'required|url|max:500',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'demo_url' => 'nullable|url|max:500',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'name' => 'theme name',
            'price' => 'price',
            'preview' => 'preview description',
            'url_thema' => 'theme URL',
            'is_active' => 'active status',
            'description' => 'description',
            'demo_url' => 'demo URL',
            'sort_order' => 'sort order',
            'features' => 'features',
            'features.*' => 'feature'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Please select a category for this theme.',
            'category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'Theme name is required.',
            'name.unique' => 'A theme with this name already exists.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'preview.required' => 'Preview description is required.',
            'url_thema.required' => 'Theme URL is required.',
            'url_thema.url' => 'Theme URL must be a valid URL.',
            'demo_url.url' => 'Demo URL must be a valid URL.',
            'features.array' => 'Features must be provided as a list.',
            'features.*.string' => 'Each feature must be text.',
            'features.*.max' => 'Each feature cannot exceed 255 characters.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        if (!$this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }

        if (!$this->has('sort_order')) {
            $this->merge(['sort_order' => 0]);
        }

        // Clean up features array
        if ($this->has('features') && is_array($this->features)) {
            $features = array_filter($this->features, function($feature) {
                return !empty(trim($feature));
            });
            $this->merge(['features' => array_values($features)]);
        }
    }
}