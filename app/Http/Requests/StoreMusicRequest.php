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
        $maxSize = config('upload.max_music_size', 51200);
        $allowedTypes = implode(',', config('upload.allowed_music_types', ['mp3', 'wav', 'ogg', 'm4a']));

        return [
            'musik' => [
                'required',
                'file',
                "mimes:{$allowedTypes}",
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
        $maxSizeMb = config('upload.max_music_size_mb', 50);

        return [
            'musik.required' => 'Music file is required.',
            'musik.file' => 'The uploaded file must be a valid file.',
            'musik.mimes' => 'Music file must be in MP3, WAV, OGG, M4A, AAC, or FLAC format.',
            'musik.max' => "Music file must not exceed {$maxSizeMb}MB."
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
            'musik' => 'music file'
        ];
    }
}
