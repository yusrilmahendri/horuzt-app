<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestimoniRequest extends FormRequest
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
        return [
            'kota' => 'required|string|min:3|max:100',
            'provinsi' => 'required|string|min:3|max:100', 
            'ulasan' => 'required|string|min:10|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kota.required' => 'Kota wajib diisi',
            'kota.min' => 'Kota minimal 3 karakter',
            'kota.max' => 'Kota maksimal 100 karakter',
            'provinsi.required' => 'Provinsi wajib diisi',
            'provinsi.min' => 'Provinsi minimal 3 karakter',
            'provinsi.max' => 'Provinsi maksimal 100 karakter',
            'ulasan.required' => 'Ulasan wajib diisi',
            'ulasan.min' => 'Ulasan minimal 10 karakter',
            'ulasan.max' => 'Ulasan maksimal 500 karakter',
        ];
    }
}