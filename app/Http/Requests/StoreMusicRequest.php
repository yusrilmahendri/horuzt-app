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
        return [
            'musik' => [
                'required',
                'file',
                'mimes:mp3,mpga,wav,wave,ogg,oga,m4a,mp4a,aac,flac,wma,webm,opus,3gp',
                'max:51200'
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
        return [
            'musik.required' => 'Music file is required.',
            'musik.file' => 'The uploaded file must be a valid file.',
            'musik.mimetypes' => 'The uploaded file must be a valid audio file (MP3, WAV, OGG, M4A, AAC, FLAC, WMA, WebM, etc.).',
            'musik.max' => 'Music file must not exceed 50MB.'
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
