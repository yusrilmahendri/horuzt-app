<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:2048', // 2MB max
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'profile_photo.required' => 'Foto profil wajib dipilih.',
            'profile_photo.image' => 'File harus berupa gambar.',
            'profile_photo.mimes' => 'Foto profil harus berformat JPEG, PNG, JPG, atau WEBP.',
            'profile_photo.max' => 'Ukuran foto profil maksimal 2MB.',
            'profile_photo.dimensions' => 'Ukuran foto minimal 100x100 pixel dan maksimal 2000x2000 pixel.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'profile_photo' => 'foto profil',
        ];
    }
}