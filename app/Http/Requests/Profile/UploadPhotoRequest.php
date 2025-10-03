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
        $maxSize = config('upload.max_file_size', 5222);
        $allowedTypes = implode(',', config('upload.allowed_image_types', ['jpeg', 'png', 'jpg', 'webp']));
        $dimensions = config('upload.image_dimensions', [
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 2000,
            'max_height' => 2000,
        ]);

        return [
            'profile_photo' => [
                'required',
                'image',
                "mimes:{$allowedTypes}",
                "max:{$maxSize}",
                "dimensions:min_width={$dimensions['min_width']},min_height={$dimensions['min_height']},max_width={$dimensions['max_width']},max_height={$dimensions['max_height']}",
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        $maxSizeMb = config('upload.max_file_size_mb', '5.1');
        $allowedTypes = strtoupper(implode(', ', config('upload.allowed_image_types', ['JPEG', 'PNG', 'JPG', 'WEBP'])));
        $dimensions = config('upload.image_dimensions', [
            'min_width' => 100,
            'min_height' => 100,
            'max_width' => 2000,
            'max_height' => 2000,
        ]);

        return [
            'profile_photo.required' => 'Foto profil wajib dipilih.',
            'profile_photo.image' => 'File harus berupa gambar.',
            'profile_photo.mimes' => "Foto profil harus berformat {$allowedTypes}.",
            'profile_photo.max' => "Ukuran foto profil maksimal {$maxSizeMb}MB.",
            'profile_photo.dimensions' => "Ukuran foto minimal {$dimensions['min_width']}x{$dimensions['min_height']} pixel dan maksimal {$dimensions['max_width']}x{$dimensions['max_height']} pixel.",
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
