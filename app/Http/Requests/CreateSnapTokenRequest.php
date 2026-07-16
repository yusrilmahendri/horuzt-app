<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateSnapTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        // Use the authenticated user's ID — no query parameter needed
        $userId = Auth::id();

        return [
            'invitation_id' => [
                'required',
                'integer',
                Rule::exists('invitations', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:10000',
                'max:100000000',
            ],
            'customer_details' => 'sometimes|array',
            'customer_details.first_name' => 'sometimes|nullable|string|max:100',
            'customer_details.last_name' => 'sometimes|nullable|string|max:100',
            'customer_details.email' => 'sometimes|nullable|email|max:255',
            'customer_details.phone' => 'sometimes|nullable|string|max:20',
            'item_details' => 'sometimes|array',
            'item_details.*.id' => 'sometimes|string|max:50',
            'item_details.*.name' => 'sometimes|string|max:255',
            'item_details.*.price' => 'sometimes|numeric|min:0',
            'item_details.*.quantity' => 'sometimes|integer|min:1',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('invitation_id')) {
                $invitation = Invitation::find($this->invitation_id);

                if ($invitation) {
                    $package = $invitation->paketUndangan;
                    if ($package && $this->amount != $package->price) {
                        $validator->errors()->add('amount', 'Nominal pembayaran tidak sesuai dengan harga paket.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'invitation_id.required' => 'Invoice wajib dipilih.',
            'invitation_id.exists' => 'Invoice tidak valid atau bukan milik Anda.',
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.min' => 'Minimal pembayaran adalah Rp 10.000.',
            'amount.max' => 'Maksimal pembayaran adalah Rp 100.000.000.',
        ];
    }
}
