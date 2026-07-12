<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

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
            'musik' => 'required|file|max:20480|extensions:mp3,wav,m4a,aac,ogg',
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
            'musik.required' => 'File musik wajib dipilih.',
            'musik.file' => 'Gagal menyimpan file musik.',
            'musik.uploaded' => 'Gagal menyimpan file musik.',
            'musik.max' => 'Ukuran file musik maksimal 20 MB.',
            'musik.extensions' => 'Format musik harus MP3, WAV, M4A, AAC, atau OGG.',
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

    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('musik');
        $errors = $validator->errors()->toArray();
        $musicMessages = isset($errors['musik']) ? collect($errors['musik']) : collect();
        $hasFormatError = $musicMessages->contains(fn ($message) => str_contains($message, 'Format musik harus'));
        $hasMaxSizeError = $musicMessages->contains(fn ($message) => str_contains($message, 'Ukuran file musik maksimal'));
        $hasRequiredError = $musicMessages->contains(fn ($message) => str_contains($message, 'File musik wajib dipilih'));
        $hasStoreError = $musicMessages->contains(fn ($message) => str_contains($message, 'Gagal menyimpan file musik'));

        $topLevelMessage = match (true) {
            $hasRequiredError => 'File musik wajib dipilih.',
            $hasFormatError => 'Format musik harus MP3, WAV, M4A, AAC, atau OGG.',
            $hasMaxSizeError => 'Ukuran file musik maksimal 20 MB.',
            $hasStoreError => 'Gagal menyimpan file musik.',
            default => 'Gagal menyimpan file musik.',
        };

        Log::warning('Custom music request validation failed', [
            'user_id' => optional($this->user())->id,
            'errors' => $errors,
            ...$this->safeFileMeta($file),
        ]);

        throw new HttpResponseException(
            response()->json([
                'message' => $topLevelMessage,
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Read uploaded file metadata safely without throwing exceptions.
     *
     * @param mixed $file
     * @return array<string,mixed>
     */
    private function safeFileMeta($file): array
    {
        $meta = [
            'original_filename' => null,
            'extension' => null,
            'client_mime_type' => null,
            'detected_mime_type' => null,
            'size' => null,
            'upload_error_code' => null,
            'upload_error_message' => null,
            'mime_detection_error' => null,
        ];

        if (!$file instanceof \Illuminate\Http\UploadedFile) {
            return $meta;
        }

        try {
            $meta['original_filename'] = $file->getClientOriginalName();
            $meta['extension'] = strtolower((string) $file->getClientOriginalExtension());
            $meta['client_mime_type'] = $file->getClientMimeType();
            $meta['size'] = $file->getSize();
            $meta['upload_error_code'] = $file->getError();
            $meta['upload_error_message'] = $file->getErrorMessage();
        } catch (\Throwable $e) {
            $meta['mime_detection_error'] = $e->getMessage();
            return $meta;
        }

        return $meta;
    }
}
