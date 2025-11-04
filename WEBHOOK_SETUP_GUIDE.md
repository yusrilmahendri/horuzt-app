# 🔧 MIDTRANS WEBHOOK SETUP GUIDE

## ❌ MASALAH

Ketika user membayar di Midtrans Snap:
- ✅ Dashboard Midtrans menunjukkan status "SUCCESS"
- ❌ Database masih menunjukkan status "PENDING"

**Root Cause:** Webhook dari Midtrans tidak sampai ke backend Anda.

---

## 🎯 SOLUSI BERDASARKAN ENVIRONMENT

### 1️⃣ LOCALHOST (Development)

Midtrans **TIDAK BISA** mengirim webhook ke `http://localhost:8000` karena localhost tidak accessible dari internet.

#### Solusi A: Manual Sync Script (Termudah)

Setelah bayar di Midtrans, jalankan script manual:

```bash
# Cari order ID yang pending
php artisan tinker --execute="
App\Models\Invitation::where('payment_status', 'pending')
    ->whereNotNull('order_id')
    ->latest()
    ->limit(5)
    ->get(['order_id', 'created_at'])
    ->each(fn(\$i) => echo \$i->order_id . ' - ' . \$i->created_at . PHP_EOL);
"

# Update status untuk order yang sudah dibayar
php test_webhook_simple.php INV-xxxxx settlement
```

#### Solusi B: Ngrok (Real Webhook Testing)

Expose localhost ke internet menggunakan ngrok:

**Step 1: Install Ngrok**
```bash
# Mac
brew install ngrok

# Windows/Linux
# Download dari https://ngrok.com/download
```

**Step 2: Run Laravel**
```bash
php artisan serve
# Running on http://127.0.0.1:8000
```

**Step 3: Run Ngrok**
```bash
# Terminal baru
ngrok http 8000

# Output:
# Forwarding https://abc123.ngrok.io -> http://localhost:8000
```

**Step 4: Update Midtrans Dashboard**
```
1. Login ke https://dashboard.midtrans.com
2. Go to: Settings → Configuration
3. Set Payment Notification URL:
   https://abc123.ngrok.io/api/v1/midtrans/webhook
4. Save
```

**Step 5: Test Payment**
```
Sekarang setiap payment akan trigger webhook ke:
https://abc123.ngrok.io → localhost:8000
```

⚠️ **CATATAN:**
- Ngrok URL berubah setiap restart (free tier)
- Harus update Midtrans Dashboard setiap restart ngrok
- Untuk fixed URL, gunakan ngrok paid plan

---

### 2️⃣ STAGING/PRODUCTION SERVER

Jika deploy ke server dengan public URL, setup permanent webhook.

#### Step 1: Verify Server Configuration

```bash
# Check server is accessible
curl -I https://yourdomain.com/api/v1/midtrans/webhook

# Should return HTTP 405 (Method Not Allowed) - this is OK
# Means endpoint exists but only accepts POST
```

#### Step 2: Set Notification URL di Midtrans Dashboard

```
1. Login ke https://dashboard.midtrans.com
2. Environment:
   - Sandbox (Testing): dashboard.sandbox.midtrans.com
   - Production: dashboard.midtrans.com

3. Go to: Settings → Configuration

4. Set URLs:
   Payment Notification URL:
   https://yourdomain.com/api/v1/midtrans/webhook

   Finish Redirect URL (optional):
   https://yourdomain.com/payment/success

   Error Redirect URL (optional):
   https://yourdomain.com/payment/error

   Pending Redirect URL (optional):
   https://yourdomain.com/payment/pending

5. Save Changes
```

#### Step 3: Test Webhook

```bash
# Make a test payment
# Then check logs:

# On server
tail -f storage/logs/laravel.log

# Or check database
php artisan tinker --execute="
App\Models\PaymentLog::where('event_type', 'webhook_received')
    ->latest()
    ->limit(5)
    ->get(['order_id', 'transaction_status', 'created_at']);
"
```

---

## 🤖 AUTOMATIC SYNC SOLUTION

Untuk development, gunakan script auto-sync yang check status di Midtrans dan update database otomatis.

### Script: Auto Sync Pending Payments

Saya akan buatkan script yang:
1. Check semua pending orders
2. Query Midtrans API untuk status
3. Update database jika sudah paid

**Coming next...**

---

## 📋 MIDTRANS DASHBOARD CONFIGURATION

### Sandbox (Testing)

```
Base URL: https://dashboard.sandbox.midtrans.com

Settings → Configuration:
  ✅ Notification URL: https://yourdomain.com/api/v1/midtrans/webhook
  ✅ Enable HTTP Notification: ON
  ✅ Email Notification: ON (optional)

Credentials:
  Server Key: SB-Mid-server-xxxxxxxxxxxxx
  Client Key: SB-Mid-client-xxxxxxxxxxxxx
```

### Production

```
Base URL: https://dashboard.midtrans.com

Settings → Configuration:
  ✅ Notification URL: https://yourdomain.com/api/v1/midtrans/webhook
  ✅ Enable HTTP Notification: ON
  ✅ Email Notification: ON (optional)

Credentials:
  Server Key: Mid-server-xxxxxxxxxxxxx
  Client Key: Mid-client-xxxxxxxxxxxxx
```

---

## 🔍 VERIFIKASI WEBHOOK SETUP

### 1. Check Endpoint Accessible

```bash
# From external
curl -X POST https://yourdomain.com/api/v1/midtrans/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"test"}'

# Should return: {"message":"Order not found"}
# This is OK - means endpoint is accessible
```

### 2. Check Webhook Logs

```bash
php artisan tinker --execute="
echo 'Total webhook received: ' . App\Models\PaymentLog::where('event_type', 'webhook_received')->count() . PHP_EOL;
echo 'Last webhook: ' . PHP_EOL;
\$last = App\Models\PaymentLog::where('event_type', 'webhook_received')->latest()->first();
if (\$last) {
    echo '  Order: ' . \$last->order_id . PHP_EOL;
    echo '  Status: ' . \$last->transaction_status . PHP_EOL;
    echo '  Time: ' . \$last->created_at . PHP_EOL;
}
"
```

### 3. Test with Real Payment

```
1. Make test payment
2. Wait 1-2 minutes
3. Check database:
   - payment_status should be "paid"
   - payment_confirmed_at should be set
   - domain_expires_at should be calculated
```

---

## 🚨 TROUBLESHOOTING

### Problem: Webhook Not Received

**Check 1: Is server accessible?**
```bash
curl -I https://yourdomain.com
```

**Check 2: Is webhook endpoint working?**
```bash
curl -X POST https://yourdomain.com/api/v1/midtrans/webhook \
  -H "Content-Type: application/json" \
  -d '{"order_id":"test"}'
```

**Check 3: Is notification URL set in Midtrans?**
- Login to Midtrans Dashboard
- Check Settings → Configuration
- Verify Notification URL is set

**Check 4: Check Laravel logs**
```bash
tail -f storage/logs/laravel.log
```

---

### Problem: Webhook Received but Not Processing

**Check payment logs:**
```bash
php artisan tinker --execute="
\$logs = App\Models\PaymentLog::where('event_type', 'error')
    ->latest()
    ->limit(5)
    ->get(['order_id', 'error_message', 'created_at']);
foreach (\$logs as \$log) {
    echo \$log->order_id . ': ' . \$log->error_message . PHP_EOL;
}
"
```

**Common errors:**
- Invalid signature → Check server_key in .env
- Order not found → Check order_id matches
- Database error → Check database connection

---

## 📝 QUICK COMMANDS

### Check Pending Orders
```bash
php artisan tinker --execute="
App\Models\Invitation::where('payment_status', 'pending')
    ->whereNotNull('order_id')
    ->get(['order_id', 'created_at'])
    ->each(fn(\$i) => echo \$i->order_id . PHP_EOL);
"
```

### Manually Update Paid Order
```bash
php test_webhook_simple.php INV-xxxxx settlement
```

### Bulk Update All Pending
```bash
php bulk_update_payments.php
```

### Check Recent Webhooks
```bash
php artisan tinker --execute="
App\Models\PaymentLog::where('event_type', 'webhook_received')
    ->latest()
    ->limit(10)
    ->get(['order_id', 'transaction_status', 'created_at'])
    ->each(fn(\$l) => echo '['.\$l->created_at.'] '.\$l->order_id.' - '.\$l->transaction_status.PHP_EOL);
"
```

---

## 🎓 BEST PRACTICES

### Development
1. ✅ Use manual sync script for quick testing
2. ✅ Use ngrok when testing webhook flow
3. ✅ Check payment_logs table after each test
4. ✅ Keep ngrok running during testing session

### Staging
1. ✅ Set permanent webhook URL in Midtrans
2. ✅ Test with sandbox credentials
3. ✅ Monitor webhook logs
4. ✅ Verify signature validation

### Production
1. ✅ Use production Midtrans credentials
2. ✅ Enable HTTPS only
3. ✅ Monitor webhook failures
4. ✅ Set up alerts for failed webhooks
5. ✅ Log all webhook attempts

---

## 📚 REFERENCES

- [Midtrans Webhook Documentation](https://docs.midtrans.com/en/after-payment/http-notification)
- [Ngrok Documentation](https://ngrok.com/docs)
- Project: `API_CONTRACT_MIDTRANS.md`
- Project: `WEBHOOK_EXPLANATION.md`

---

**Created:** 2025-11-03
**Updated:** 2025-11-03
**Status:** Production Ready
