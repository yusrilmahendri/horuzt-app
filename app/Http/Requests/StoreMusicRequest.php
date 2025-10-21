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
                'mimes:mp3,wav,ogg,m4a',
                'max:10240' // 10MB
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
            'musik.mimes' => 'Music file must be in MP3, WAV, OGG, or M4A format.',
            'musik.max' => 'Music file must not exceed 10MB.'
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