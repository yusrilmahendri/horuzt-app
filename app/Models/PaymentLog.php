<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invitation_id',
        'order_id',
        'midtrans_transaction_id',
        'event_type',
        'transaction_status',
        'payment_type',
        'gross_amount',
        'request_payload',
        'response_payload',
        'signature_key',
        'signature_valid',
        'ip_address',
        'user_agent',
        'error_message',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'signature_valid' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitation()
    {
        return $this->belongsTo(Invitation::class);
    }

    public function scopeByOrderId($query, string $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeWebhooks($query)
    {
        return $query->whereIn('event_type', ['webhook_received', 'webhook_processed']);
    }

    public function scopeErrors($query)
    {
        return $query->where('event_type', 'error');
    }

    public function scopeInvalidSignatures($query)
    {
        return $query->where('signature_valid', false);
    }
}
