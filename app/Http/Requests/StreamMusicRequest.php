<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StreamMusicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public access for streaming
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                'exists:settings,id'
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
            'id.required' => 'Setting ID is required.',
            'id.integer' => 'Setting ID must be a valid integer.',
            'id.exists' => 'The specified setting does not exist.'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Get ID from query parameter if not in request body
        if (!$this->has('id') && $this->query('id')) {
            $this->merge(['id' => $this->query('id')]);
        }
    }
}