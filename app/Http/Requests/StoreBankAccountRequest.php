<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = config('upload.max_file_size', 51200);
        $allowedTypes = implode(',', config('upload.allowed_image_types', ['jpeg', 'png', 'jpg', 'webp']));

        return [
            'kode_bank' => 'required|string|exists:banks,kode_bank',
            'nomor_rekening' => 'required|string|min:8|max:20|regex:/^[0-9]+$/',
            'nama_pemilik' => 'required|string|min:3|max:100',
            'photo_rek' => "nullable|image|mimes:{$allowedTypes}|max:{$maxSize}",
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMb = config('upload.max_file_size_mb', 50);

        return [
            'kode_bank.required' => 'Bank wajib dipilih',
            'kode_bank.exists' => 'Bank yang dipilih tidak valid',
            'nomor_rekening.required' => 'Nomor rekening wajib diisi',
            'nomor_rekening.min' => 'Nomor rekening minimal 8 digit',
            'nomor_rekening.max' => 'Nomor rekening maksimal 20 digit',
            'nomor_rekening.regex' => 'Nomor rekening hanya boleh berisi angka',
            'nama_pemilik.required' => 'Nama pemilik rekening wajib diisi',
            'nama_pemilik.min' => 'Nama pemilik minimal 3 karakter',
            'nama_pemilik.max' => 'Nama pemilik maksimal 100 karakter',
            'photo_rek.image' => 'File harus berupa gambar',
            'photo_rek.mimes' => 'Format gambar yang diizinkan: jpeg, png, jpg, webp',
            'photo_rek.max' => "Ukuran gambar maksimal {$maxSizeMb}MB",
        ];
    }
}
