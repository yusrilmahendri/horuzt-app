<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMusicRequest extends FormRequest
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
        $maxSize = config('upload.music_max_file_size', 10240);

        return [
            'musik' => [
                'required',
                'file',
                'mimes:mp3,wav,ogg,m4a',
                "max:{$maxSize}"
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMb = config('upload.music_max_file_size_mb', '10');

        return [
            'musik.required' => 'File musik wajib diunggah.',
            'musik.file' => 'File musik tidak valid.',
            'musik.uploaded' => 'Gagal mengunggah file musik.',
            'musik.mimes' => 'Format file musik tidak didukung. Gunakan MP3, WAV, OGG, atau M4A.',
            'musik.max' => "Ukuran file musik melebihi batas maksimum {$maxSizeMb} MB."
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'musik' => 'file musik'
        ];
    }
}
