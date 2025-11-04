# 🚀 MIDTRANS WEBHOOK - QUICK REFERENCE

## ❓ SIAPA YANG HIT `/v1/midtrans/webhook`?

```
┌─────────────────────────────────────────────┐
│  JAWABAN: MIDTRANS SERVER                   │
│  BUKAN Frontend, BUKAN Backend Anda         │
└─────────────────────────────────────────────┘
```

---

## 📊 SIMPLE FLOW DIAGRAM

```
USER                 FRONTEND              YOUR BACKEND           MIDTRANS
 │                      │                       │                     │
 │ 1. Klik "Bayar"      │                       │                     │
 │─────────────────────>│                       │                     │
 │                      │                       │                     │
 │                      │ 2. Request Token      │                     │
 │                      │──────────────────────>│                     │
 │                      │                       │                     │
 │                      │                       │ 3. Create Token     │
 │                      │                       │────────────────────>│
 │                      │                       │                     │
 │                      │                       │ 4. Snap Token       │
 │                      │<──────────────────────│<────────────────────│
 │                      │                       │                     │
 │ 5. Show Popup        │                       │                     │
 │<─────────────────────│                       │                     │
 │                      │                       │                     │
 │ 6. Bayar di Popup    │                       │                     │
 │─────────────────────>│                       │                     │
 │                      │                       │                     │
 │                      │       7. Process Payment                    │
 │                      │                       │<────────────────────│
 │                      │                       │                     │
 │                      │    ⭐ 8. WEBHOOK (MIDTRANS → YOUR BACKEND)  │
 │                      │                       │<────────────────────│
 │                      │                       │                     │
 │                      │                       │ 9. Update DB        │
 │                      │                       │ payment_status=paid │
 │                      │                       │                     │
 │                      │                       │ 10. Return 200 OK   │
 │                      │                       │────────────────────>│
 │                      │                       │                     │
 │ 11. Check Status     │                       │                     │
 │─────────────────────>│──────────────────────>│                     │
 │                      │                       │                     │
 │ 12. Status = PAID ✅ │                       │                     │
 │<─────────────────────│<──────────────────────│                     │
```

---

## 🎯 WHO CALLS WHO?

| Endpoint | Called By | Target | Purpose |
|----------|-----------|--------|---------|
| `POST /midtrans/create-snap-token` | **Frontend** | Your Backend | Get payment token |
| `POST /v1/midtrans/webhook` | **Midtrans Server** ⭐ | Your Backend | Payment notification |
| `GET /v1/user-profile` | **Frontend** | Your Backend | Check payment status |

---

## ⏰ KAPAN MIDTRANS HIT WEBHOOK?

| Event | Status | Timing | Action |
|-------|--------|--------|--------|
| Payment Success | `settlement` | 1-2 menit setelah bayar | Update ke `paid` |
| Payment Pending | `pending` | Immediately | Keep `pending` |
| Payment Failed | `deny`, `cancel`, `expire` | When failed | Update ke `failed` |
| Payment Refund | `refund` | After refund | Update ke `refunded` |

---

## 📦 WEBHOOK PAYLOAD (dari Midtrans)

```json
{
  "order_id": "INV-550e8400-...",
  "transaction_status": "settlement",
  "transaction_id": "midtrans-tx-123",
  "gross_amount": "299000.00",
  "signature_key": "8f2d7c9e...",
  "payment_type": "credit_card"
}
```

---

## 🔐 SECURITY

### Webhook TIDAK pakai Bearer Token!

Webhook menggunakan **signature verification**:

```php
$signature = hash('sha512', $orderId . $statusCode . $amount . $serverKey);

if ($signature !== $request->signature_key) {
    return 403; // Invalid signature
}
```

---

## ❌ COMMON MISTAKES

| ❌ Wrong | ✅ Correct |
|----------|-----------|
| Frontend hit `/v1/midtrans/webhook` | Midtrans Server hit webhook |
| Webhook butuh Bearer token | Webhook pakai signature verification |
| Webhook hit synchronously | Webhook hit async (1-2 menit delay) |
| Test webhook di localhost | Use ngrok atau manual script |

---

## 🧪 TESTING DI LOCALHOST

### Problem:
Midtrans tidak bisa hit `http://localhost:8000` karena localhost tidak public.

### Solutions:

#### 1. Manual Script (Recommended for Development)
```bash
php test_webhook_simple.php INV-your-order-id settlement
```

#### 2. Ngrok (Real Webhook Testing)
```bash
# Terminal 1
php artisan serve

# Terminal 2
ngrok http 8000

# Update Midtrans Dashboard:
# Notification URL: https://abc123.ngrok.io/api/v1/midtrans/webhook
```

#### 3. Production Server
Deploy ke server dengan public URL.

---

## 📝 FRONTEND CHECKLIST

### ✅ Yang Harus Frontend Lakukan:

```javascript
// 1. Request Snap Token
const { snap_token } = await createSnapToken({
  invitation_id: 5,
  amount: 299000
});

// 2. Open Midtrans Popup
snap.pay(snap_token, {
  onSuccess: (result) => {
    // Redirect ke success page
    // Backend AKAN dapat webhook dari Midtrans
  }
});

// 3. Check Payment Status
const profile = await getUserProfile();
if (profile.invitation.payment_status === 'paid') {
  // ✅ Payment berhasil!
}
```

### ❌ Yang TIDAK Boleh Frontend Lakukan:

```javascript
// ❌ JANGAN hit webhook endpoint
fetch('/api/v1/midtrans/webhook', {
  method: 'POST',
  body: {...}
}); // WRONG! Only Midtrans should call this
```

---

## 🔍 DEBUG COMMANDS

### Check Webhook Received:
```bash
php artisan tinker --execute="
App\Models\PaymentLog::where('event_type', 'webhook_received')
    ->latest()->limit(5)
    ->get(['order_id', 'transaction_status', 'created_at']);
"
```

### Check Payment Status:
```bash
php artisan tinker --execute="
App\Models\Invitation::where('order_id', 'INV-xxx')
    ->first(['payment_status', 'payment_confirmed_at']);
"
```

### Manual Webhook Trigger:
```bash
php test_webhook_simple.php INV-xxx settlement
```

---

## 📋 WEBHOOK RESPONSE CODES

| Code | Meaning | When |
|------|---------|------|
| 200 | Success | Webhook processed |
| 403 | Forbidden | Invalid signature |
| 404 | Not Found | Order not found |
| 500 | Error | Processing failed |

---

## 💡 KEY TAKEAWAYS

1. **Webhook = Callback dari Midtrans ke Backend Anda**
2. **Frontend TIDAK pernah hit webhook**
3. **Webhook menggunakan signature, bukan bearer token**
4. **Webhook hit async (ada delay 1-2 menit)**
5. **Di localhost, webhook tidak akan sampai → pakai ngrok atau manual script**

---

**Created:** 2025-11-03
**For:** Development Reference
