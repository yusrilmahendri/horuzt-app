<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
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
        $maxSize = config('upload.music_max_file_size', 10240);
        $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a'];

        return [
            'musik' => [
                'required',
                'file',
                "max:{$maxSize}",
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedExtensions): void {
                    if (!$value instanceof UploadedFile) {
                        $fail('File musik wajib dipilih.');
                        return;
                    }

                    if (!$value->isValid()) {
                        $fail('File musik tidak valid atau tidak dapat diproses.');
                        return;
                    }

                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                        $fail('Format file musik tidak didukung. Gunakan MP3, WAV, OGG, atau M4A.');
                    }
                },
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
            'musik.required' => 'File musik wajib dipilih.',
            'musik.file' => 'File musik tidak valid atau tidak dapat diproses.',
            'musik.uploaded' => 'File musik tidak valid atau tidak dapat diproses.',
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

    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('musik');
        $errors = $validator->errors()->toArray();
        $maxSizeMb = config('upload.music_max_file_size_mb', '10');
        $musicMessages = isset($errors['musik']) ? collect($errors['musik']) : collect();
        $hasFormatError = $musicMessages->contains(fn ($message) => str_contains($message, 'Format file musik tidak didukung'));
        $hasMaxSizeError = $musicMessages->contains(fn ($message) => str_contains($message, 'Ukuran file musik melebihi batas maksimum'));
        $hasRequiredError = $musicMessages->contains(fn ($message) => str_contains($message, 'File musik wajib dipilih'));

        $topLevelMessage = match (true) {
            $hasRequiredError => 'File musik wajib dipilih.',
            $hasFormatError => 'Format file musik tidak didukung. Gunakan MP3, WAV, OGG, atau M4A.',
            $hasMaxSizeError => "Ukuran file musik melebihi batas maksimum {$maxSizeMb} MB.",
            default => 'File musik tidak valid atau tidak dapat diproses.',
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

        if (!$file instanceof UploadedFile) {
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
