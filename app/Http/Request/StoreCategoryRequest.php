<?php

// App\Http\Requests\CategoryThemas\StoreCategoryRequest.php

namespace App\Http\Requests\CategoryThemas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:category_themas,name',
            'slug' => 'nullable|string|max:255|unique:category_themas,slug',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required',
            'name.unique' => 'Category name already exists',
            'slug.unique' => 'Category slug already exists',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->slug && $this->name) {
            $this->merge([
                'slug' => Str::slug($this->name)
            ]);
        }
    }
}

// App\Http\Requests\CategoryThemas\UpdateCategoryRequest.php

namespace App\Http\Requests\CategoryThemas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('category_themas', 'name')->ignore($categoryId)
            ],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('category_themas', 'slug')->ignore($categoryId)
            ],
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required',
            'name.unique' => 'Category name already exists',
            'slug.unique' => 'Category slug already exists',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->slug && $this->name) {
            $this->merge([
                'slug' => Str::slug($this->name)
            ]);
        }
    }
}

// App\Http\Requests\Themas\StoreThemaRequest.php

namespace App\Http\Requests\Themas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreThemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:themas,slug',
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|exists:category_themas,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'demo_url' => 'nullable|url|max:500',
            'download_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'featured' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Theme name is required',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'image.image' => 'File must be an image',
            'image.max' => 'Image size must not exceed 2MB',
            'demo_url.url' => 'Demo URL must be a valid URL',
            'download_url.url' => 'Download URL must be a valid URL',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->slug && $this->name) {
            $this->merge([
                'slug' => Str::slug($this->name)
            ]);
        }

        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'featured' => $this->boolean('featured', false),
        ]);
    }
}

// App\Http\Requests\Themas\UpdateThemaRequest.php

namespace App\Http\Requests\Themas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateThemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $themaId = $this->route('thema')->id;

        return [
            'name' => 'required|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('themas', 'slug')->ignore($themaId)
            ],
            'description' => 'nullable|string|max:2000',
            'category_id' => 'required|exists:category_themas,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'demo_url' => 'nullable|url|max:500',
            'download_url' => 'nullable|url|max:500',
            'is_active' => 'boolean',
            'featured' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Theme name is required',
            'category_id.required' => 'Category is required',
            'category_id.exists' => 'Selected category does not exist',
            'image.image' => 'File must be an image',
            'image.max' => 'Image size must not exceed 2MB',
            'demo_url.url' => 'Demo URL must be a valid URL',
            'download_url.url' => 'Download URL must be a valid URL',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->slug && $this->name) {
            $this->merge([
                'slug' => Str::slug($this->name)
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->has('featured')) {
            $this->merge(['featured' => $this->boolean('featured')]);
        }
    }
}
