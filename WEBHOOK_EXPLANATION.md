# MIDTRANS WEBHOOK EXPLANATION

## ❓ Pertanyaan: Siapa yang Hit `/v1/midtrans/webhook`?

**Jawaban:** **MIDTRANS SERVER** yang hit endpoint webhook, bukan frontend atau backend Anda!

---

## 🔄 COMPLETE PAYMENT FLOW

### Step-by-Step Detailed Flow:

```
┌─────────────────────────────────────────────────────────────────────┐
│ 1. USER INITIATES PAYMENT (Frontend)                               │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ User clicks "Bayar Sekarang"
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 2. FRONTEND → BACKEND                                               │
│    POST /midtrans/create-snap-token                                 │
│    {                                                                 │
│      "invitation_id": 5,                                            │
│      "amount": 299000                                               │
│    }                                                                 │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Frontend calls YOUR backend
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 3. YOUR BACKEND → MIDTRANS API                                      │
│    Your backend calls Midtrans Snap API                             │
│    to generate payment token                                        │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Midtrans returns snap_token
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 4. BACKEND → FRONTEND                                               │
│    Response:                                                         │
│    {                                                                 │
│      "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",          │
│      "order_id": "INV-550e8400-e29b-41d4-a716-446655440000"         │
│    }                                                                 │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Frontend receives snap_token
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 5. FRONTEND → MIDTRANS SNAP                                         │
│    Frontend opens Midtrans payment popup:                           │
│    snap.pay(snap_token, {...})                                      │
│                                                                      │
│    User sees:                                                        │
│    - Payment methods (Credit Card, Bank Transfer, etc)              │
│    - Amount to pay                                                   │
│    - Order details                                                   │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ User completes payment at Midtrans
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 6. MIDTRANS PROCESSES PAYMENT                                       │
│    Midtrans server:                                                  │
│    - Validates payment                                               │
│    - Charges card/processes transfer                                 │
│    - Updates transaction status                                      │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Payment successful!
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 7. ⭐ MIDTRANS SERVER → YOUR BACKEND WEBHOOK ⭐                      │
│    POST https://yourdomain.com/api/v1/midtrans/webhook              │
│    {                                                                 │
│      "order_id": "INV-550e8400-...",                                │
│      "transaction_status": "settlement",                             │
│      "transaction_id": "midtrans-tx-1234567890",                    │
│      "gross_amount": "299000.00",                                   │
│      "signature_key": "8f2d7c9e1b4a5f6d3c8e...",                    │
│      "payment_type": "credit_card"                                   │
│    }                                                                 │
│                                                                      │
│    ⚠️  PENTING:                                                      │
│    - MIDTRANS yang kirim request ini                                │
│    - BUKAN frontend                                                  │
│    - BUKAN backend Anda                                              │
│    - Ini adalah CALLBACK dari Midtrans                               │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Your backend receives webhook
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 8. YOUR BACKEND PROCESSES WEBHOOK                                   │
│    - Verify signature (security)                                    │
│    - Find invitation by order_id                                     │
│    - Update payment_status to "paid"                                │
│    - Set payment_confirmed_at                                        │
│    - Calculate domain_expires_at                                     │
│    - Save to database                                                │
│    - Log to payment_logs                                             │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Backend updates database
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 9. YOUR BACKEND → MIDTRANS                                          │
│    Response: 200 OK                                                  │
│    { "message": "Webhook processed successfully" }                   │
└─────────────────────────────────────────────────────────────────────┘
   │
   │ Meanwhile, user closes Midtrans popup
   │
   ▼
┌─────────────────────────────────────────────────────────────────────┐
│ 10. FRONTEND CHECKS PAYMENT STATUS                                  │
│     GET /v1/user-profile                                             │
│     Response shows: payment_status = "paid" ✅                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 SIAPA HIT SIAPA?

### Endpoint: `/midtrans/create-snap-token`
- **Siapa yang hit:** **FRONTEND** Anda
- **Ke mana:** **BACKEND** Anda
- **Kapan:** Ketika user klik tombol "Bayar Sekarang"
- **Tujuan:** Mendapatkan snap_token untuk payment

### Endpoint: `/v1/midtrans/webhook`
- **Siapa yang hit:** **MIDTRANS SERVER** ⚠️
- **Ke mana:** **BACKEND** Anda
- **Kapan:**
  - Setelah payment selesai (settlement)
  - Ketika payment status berubah
  - Async/background (tidak langsung)
- **Tujuan:** Memberitahu backend bahwa payment sudah selesai

---

## 📋 WEBHOOK REQUEST PARAMETERS (dari Midtrans)

Midtrans akan mengirim payload seperti ini:

```json
{
  "transaction_time": "2025-11-03 12:00:00",
  "transaction_status": "settlement",
  "transaction_id": "midtrans-tx-1234567890",
  "status_message": "midtrans payment success",
  "status_code": "200",
  "signature_key": "8f2d7c9e1b4a5f6d3c8e7a9b2d5f1e4c3b6a9d2e5f8a1c4d7b0e3f6a9c2d5e8f1",
  "settlement_time": "2025-11-03 12:01:00",
  "payment_type": "credit_card",
  "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
  "merchant_id": "G123456789",
  "gross_amount": "299000.00",
  "fraud_status": "accept",
  "currency": "IDR",
  "card_type": "credit",
  "bank": "bni"
}
```

### Key Parameters:

| Parameter | Description | Example |
|-----------|-------------|---------|
| `order_id` | Order ID yang Anda generate | `INV-550e8400-...` |
| `transaction_status` | Status dari Midtrans | `settlement`, `pending`, `deny` |
| `transaction_id` | ID transaksi dari Midtrans | `midtrans-tx-1234567890` |
| `gross_amount` | Total pembayaran | `299000.00` |
| `signature_key` | Security signature | `8f2d7c9e1b4a...` |
| `payment_type` | Metode pembayaran | `credit_card`, `bank_transfer` |
| `status_code` | HTTP status code | `200` |

---

## 🔐 SIGNATURE VERIFICATION

Webhook endpoint TIDAK menggunakan authentication bearer token karena ini adalah external callback dari Midtrans.

Sebagai gantinya, menggunakan **signature verification**:

```php
// Backend verifies signature
$signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
$calculatedSignature = hash('sha512', $signatureString);

if ($calculatedSignature !== $signature_key_from_midtrans) {
    return response()->json(['message' => 'Invalid signature'], 403);
}
```

Ini memastikan bahwa request benar-benar dari Midtrans, bukan dari hacker.

---

## ⏰ KAPAN MIDTRANS HIT WEBHOOK?

Midtrans akan hit webhook endpoint Anda pada event berikut:

### 1. **Payment Settlement (Berhasil)**
- User berhasil bayar
- Status: `settlement` atau `capture`
- Webhook dikirim: **1-2 menit** setelah payment
- Backend action: Update status ke `paid`

### 2. **Payment Pending**
- User pilih bank transfer tapi belum bayar
- Status: `pending`
- Webhook dikirim: **Immediately** setelah create
- Backend action: Keep status `pending`

### 3. **Payment Failed**
- Payment ditolak/gagal/expired
- Status: `deny`, `cancel`, `expire`
- Webhook dikirim: When status changes
- Backend action: Update status ke `failed`

### 4. **Payment Refund**
- Admin melakukan refund
- Status: `refund`
- Webhook dikirim: After refund processed
- Backend action: Update status ke `refunded`

---

## 🚨 KENAPA WEBHOOK TIDAK SAMPAI DI LOCALHOST?

**Problem:** Midtrans server tidak bisa hit `http://localhost:8000` karena localhost hanya accessible dari komputer Anda.

**Solutions:**

### Solution 1: Ngrok (Development)
```bash
# Terminal 1: Run Laravel
php artisan serve

# Terminal 2: Run ngrok
ngrok http 8000

# Ngrok memberikan public URL:
# https://abc123.ngrok.io

# Set di Midtrans Dashboard:
# Notification URL: https://abc123.ngrok.io/api/v1/midtrans/webhook
```

### Solution 2: Manual Testing (Development)
```bash
# Gunakan script yang sudah dibuat:
php test_webhook_simple.php INV-xxx settlement
```

### Solution 3: Deploy ke Server (Production)
```
Deploy ke server dengan public URL
Notification URL: https://yourdomain.com/api/v1/midtrans/webhook
```

---

## 📊 TRANSACTION STATUS MAPPING

| Midtrans Status | Meaning | Database Status | Domain Action |
|-----------------|---------|-----------------|---------------|
| `capture` | CC payment authorized | `paid` | Set expiry date |
| `settlement` | Payment completed | `paid` | Set expiry date |
| `pending` | Waiting for payment | `pending` | No action |
| `challenge` | Fraud detection check | `pending` | No action |
| `deny` | Payment rejected | `failed` | No action |
| `cancel` | User cancelled | `failed` | No action |
| `expire` | Payment expired | `failed` | No action |
| `refund` | Payment refunded | `refunded` | No action |

---

## 🔍 DEBUGGING WEBHOOK

### Check if Webhook is Received:

```bash
# Check payment logs
php artisan tinker --execute="
App\Models\PaymentLog::where('event_type', 'webhook_received')
    ->latest()
    ->limit(5)
    ->get(['order_id', 'transaction_status', 'created_at'])
    ->each(fn(\$l) => echo \$l->order_id . ' - ' . \$l->transaction_status . PHP_EOL);
"
```

### Check Webhook Configuration:

```bash
# Verify Midtrans config
php artisan tinker --execute="
echo 'Server Key: ' . config('midtrans.server_key') . PHP_EOL;
echo 'Is Production: ' . (config('midtrans.is_production') ? 'YES' : 'NO') . PHP_EOL;
"
```

### Manually Trigger Webhook (Testing):

```bash
php test_webhook_simple.php INV-your-order-id settlement
```

---

## 📝 FRONTEND INTEGRATION (TIDAK HIT WEBHOOK!)

**PENTING:** Frontend **TIDAK PERNAH** hit `/v1/midtrans/webhook`

Frontend hanya:

### 1. Request Snap Token
```javascript
// Frontend hits YOUR backend
const response = await fetch('/api/midtrans/create-snap-token', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    invitation_id: 5,
    amount: 299000
  })
});

const { snap_token } = await response.json();
```

### 2. Open Midtrans Snap Payment
```javascript
// Frontend loads Midtrans Snap UI
snap.pay(snap_token, {
  onSuccess: function(result) {
    // Redirect ke success page
    // Backend akan dapat webhook dari Midtrans
    window.location.href = '/payment/success';
  },
  onPending: function(result) {
    window.location.href = '/payment/pending';
  },
  onError: function(result) {
    window.location.href = '/payment/error';
  },
  onClose: function() {
    console.log('User closed popup');
  }
});
```

### 3. Check Payment Status
```javascript
// After payment, check status from YOUR backend
const checkStatus = await fetch('/api/v1/user-profile', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

const userData = await checkStatus.json();
if (userData.invitation.payment_status === 'paid') {
  // Payment successful!
}
```

---

## 🎬 SUMMARY

### Yang Hit `/v1/midtrans/webhook`:
**MIDTRANS SERVER** ✅

### Kapan Hit:
- Setelah payment selesai (1-2 menit)
- Ketika status berubah
- Secara async/background

### Parameters:
- Dikirim oleh Midtrans
- Berisi order_id, transaction_status, signature, dll
- Format JSON (lihat payload di atas)

### Frontend Role:
- ❌ **TIDAK** hit webhook
- ✅ Request snap token dari backend
- ✅ Open Midtrans popup
- ✅ Check payment status setelah bayar

### Backend Role:
- ✅ Generate snap token
- ✅ **RECEIVE** webhook dari Midtrans
- ✅ Verify signature
- ✅ Update database
- ✅ Return 200 OK ke Midtrans

---

**Last Updated:** 2025-11-03
**Version:** 1.0
