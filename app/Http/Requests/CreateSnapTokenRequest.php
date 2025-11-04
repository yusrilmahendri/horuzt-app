<?php

namespace App\Http\Requests;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSnapTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // Validate user_id query parameter exists
        if (!$this->query('user_id')) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], [
                    'user_id' => 'required'
                ], [
                    'user_id.required' => 'User ID is required in query parameter (?user_id=X)'
                ])
            );
        }
    }

    public function rules(): array
    {
        // Get user_id from query parameter
        $userId = $this->query('user_id');

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
                    if ($invitation->payment_status === 'paid') {
                        $validator->errors()->add('invitation_id', 'This invitation has already been paid.');
                    }

                    if ($invitation->order_id) {
                        $validator->errors()->add('invitation_id', 'Payment already initiated for this invitation.');
                    }

                    $package = $invitation->paketUndangan;
                    if ($package && $this->amount != $package->price) {
                        $validator->errors()->add('amount', 'Amount does not match package price.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'invitation_id.required' => 'Invitation ID is required.',
            'invitation_id.exists' => 'Invalid invitation or you do not have permission to access this invitation.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Minimum payment amount is Rp 10,000.',
            'amount.max' => 'Maximum payment amount is Rp 100,000,000.',
        ];
    }
}
