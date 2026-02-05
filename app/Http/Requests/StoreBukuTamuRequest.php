<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBukuTamuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'nama' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'ucapan' => ['nullable', 'string', 'min:5', 'max:1000'],
            'status_kehadiran' => ['required', Rule::in(['hadir', 'tidak_hadir', 'ragu'])],
            'jumlah_tamu' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'ID undangan wajib diisi.',
            'user_id.exists' => 'Undangan tidak ditemukan.',
            'nama.required' => 'Nama wajib diisi.',
            'nama.min' => 'Nama minimal 2 karakter.',
            'nama.max' => 'Nama maksimal 100 karakter.',
            'email.email' => 'Format email tidak valid.',
            'ucapan.min' => 'Ucapan minimal 5 karakter.',
            'ucapan.max' => 'Ucapan maksimal 1000 karakter.',
            'status_kehadiran.required' => 'Status kehadiran wajib dipilih.',
            'status_kehadiran.in' => 'Status kehadiran tidak valid.',
            'jumlah_tamu.min' => 'Jumlah tamu minimal 1 orang.',
            'jumlah_tamu.max' => 'Jumlah tamu maksimal 20 orang.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'jumlah_tamu' => $this->jumlah_tamu ?? 1,
        ]);
    }
}
