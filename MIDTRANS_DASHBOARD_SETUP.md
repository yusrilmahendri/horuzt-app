# MIDTRANS DASHBOARD CONFIGURATION GUIDE

Complete step-by-step guide to configure Midtrans Dashboard for webhook notifications.

---

## CRITICAL: Payment Notification URL Configuration

The **Payment Notification URL** is the most critical setting in Midtrans. Without this, your application will never receive payment confirmations and all payments will remain in "pending" status forever.

---

## CURRENT CONFIGURATION STATUS

Based on your sandbox dashboard screenshot:

| Setting | Current Value | Status |
|---------|---------------|--------|
| Merchant ID | G178483914 | ✅ Configured |
| Client Key | SB-Mid-client-NjshfjUODw5Zt75 | ✅ Configured |
| Server Key | SB-Mid-server-zhq3W8mxs5WfkF14vqQltObC | ✅ Configured |
| **Payment Notification URL** | ❌ **EMPTY** | ❌ **CRITICAL - MUST BE SET!** |
| Finish Redirect URL | https://www.sena-digital.com/dashboard/overview | ✅ Configured |
| Unfinish Redirect URL | https://www.sena-digital.com/buat-undangan | ✅ Configured |
| Error Redirect URL | https://www.sena-digital.com/buat-undangan | ✅ Configured |

---

## STEP-BY-STEP CONFIGURATION

### 1. Login to Midtrans Dashboard

**Sandbox (Testing):**
- URL: https://dashboard.sandbox.midtrans.com
- Use your sandbox credentials

**Production (Live):**
- URL: https://dashboard.midtrans.com
- Use your production credentials

---

### 2. Navigate to Settings

1. After login, click on **Settings** in the left sidebar
2. Select **Configuration** or **Settings** tab
3. Look for **"Setting URL endpoints"** section

---

### 3. Set Payment Notification URL

This is the webhook endpoint where Midtrans will send payment status updates.

**For Sandbox/Development:**
```
https://www.sena-digital.com/api/v1/midtrans/webhook
```

**For Production:**
```
https://www.sena-digital.com/api/v1/midtrans/webhook
```

**Important Notes:**
- ⚠️ This URL must be **publicly accessible** from the internet
- ⚠️ Midtrans servers must be able to reach this URL
- ⚠️ Do NOT use `localhost` or `127.0.0.1`
- ⚠️ Must use HTTPS (not HTTP) for production
- ✅ For sandbox, HTTP is acceptable but HTTPS is recommended

---

### 4. Optional: Set Recurring Notification URL

If you plan to use subscription/recurring payments in the future:

```
https://www.sena-digital.com/api/v1/midtrans/webhook/recurring
```

**Note:** This is currently not implemented in the code, so you can leave it empty for now.

---

### 5. Optional: Set Pay Account Notification URL

If you plan to use GoPay or other e-wallet accounts:

```
https://www.sena-digital.com/api/v1/midtrans/webhook/account
```

**Note:** This is currently not implemented in the code, so you can leave it empty for now.

---

### 6. Verify Redirect URLs

These are already configured in your dashboard, but double-check they match:

| URL Type | Value |
|----------|-------|
| **Finish Redirect URL** | https://www.sena-digital.com/dashboard/overview |
| **Unfinish Redirect URL** | https://www.sena-digital.com/buat-undangan |
| **Error Redirect URL** | https://www.sena-digital.com/buat-undangan |

**What these URLs do:**
- **Finish:** Where users are redirected after successful payment
- **Unfinish:** Where users are redirected if they close the payment window
- **Error:** Where users are redirected if payment fails

---

### 7. Save Configuration

1. Click **"Save & Next"** or **"Save"** button at the bottom
2. You should see a success message
3. Wait a few minutes for changes to propagate

---

## TESTING WEBHOOK CONFIGURATION

After configuring, test the webhook endpoint:

### Test 1: Check Endpoint is Accessible

```bash
curl -X POST https://www.sena-digital.com/api/v1/midtrans/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "TEST-ORDER",
    "transaction_status": "settlement",
    "transaction_id": "test-123",
    "status_code": "200",
    "signature_key": "invalid",
    "gross_amount": "100000.00"
  }'
```

**Expected Response:**
- Status: 404 (Order not found) or 403 (Invalid signature)
- This is correct! It means the endpoint is working.

**Bad Response:**
- Connection timeout or 404 (Not Found)
- This means the URL is not accessible

---

### Test 2: Complete Payment Flow Test

Follow the complete testing guide in `MIDTRANS_API_TESTING.md`:

```bash
./test_midtrans.sh
```

This will:
1. Create a snap token
2. Simulate payment
3. Send webhook notification
4. Verify payment status is updated to "paid"

---

## UNDERSTANDING WEBHOOK FLOW

```
┌─────────────────────────────────────────────────────────────────┐
│                     HOW WEBHOOKS WORK                            │
└─────────────────────────────────────────────────────────────────┘

1. User pays via Midtrans popup
        ↓
2. Midtrans processes payment on their servers
        ↓
3. Midtrans sends HTTP POST to Payment Notification URL
   POST https://www.sena-digital.com/api/v1/midtrans/webhook
   Body: {
     "order_id": "INV-xxx",
     "transaction_status": "settlement",
     "signature_key": "...",
     ...
   }
        ↓
4. Your backend verifies signature (security check)
        ↓
5. Your backend updates database:
   - payment_status = 'paid'
   - payment_confirmed_at = now()
   - domain_expires_at = now() + package duration
        ↓
6. Your backend returns 200 OK to Midtrans
        ↓
7. Midtrans marks webhook as delivered
        ↓
8. User sees updated status in app
```

---

## WEBHOOK RETRY MECHANISM

If your webhook endpoint is down or returns an error:

1. Midtrans will retry sending the webhook
2. Retry schedule:
   - Immediately (0 seconds)
   - 1 minute
   - 2 minutes
   - 5 minutes
   - 30 minutes
   - 1 hour
   - 2 hours
   - 6 hours
   - 24 hours

3. After all retries fail, webhook is marked as failed
4. You can manually trigger webhook resend from dashboard

---

## TROUBLESHOOTING

### Problem: Webhook Never Received

**Possible Causes:**
1. Payment Notification URL not configured in dashboard
2. URL is not publicly accessible (using localhost)
3. Server firewall blocking Midtrans IPs
4. SSL certificate error (for HTTPS)

**Solutions:**
1. ✅ Set Payment Notification URL in dashboard
2. ✅ Use public domain (www.sena-digital.com)
3. ✅ Whitelist Midtrans IPs (if using firewall)
4. ✅ Ensure valid SSL certificate

---

### Problem: Webhook Received but Returns 403 (Invalid Signature)

**Possible Causes:**
1. Server key mismatch between .env and dashboard
2. Gross amount format incorrect
3. Order ID mismatch

**Solutions:**
1. ✅ Verify MIDTRANS_SERVER_KEY in .env matches dashboard
2. ✅ Check signature calculation in logs
3. ✅ Ensure order_id exists in database

---

### Problem: Webhook Received but Payment Status Not Updated

**Possible Causes:**
1. Order ID not found in database
2. Database transaction failed
3. Validation errors

**Solutions:**
1. ✅ Check `payment_logs` table for error messages
2. ✅ Review Laravel logs: `storage/logs/laravel.log`
3. ✅ Check invitation table for order_id

---

## SECURITY CONSIDERATIONS

### 1. Signature Verification

Your application automatically verifies webhook signatures using:

```php
SHA512(order_id + status_code + gross_amount + server_key)
```

This ensures the webhook is genuinely from Midtrans.

### 2. Idempotency

Your application prevents duplicate processing of the same webhook:

- If payment status is already "paid" and webhook status is "settlement", returns "Already processed"
- This prevents double-charging or data corruption

### 3. IP Whitelisting (Optional)

For additional security, you can whitelist Midtrans IPs in your firewall:

**Midtrans Sandbox IPs:**
- 103.127.16.0/23
- 103.208.22.0/24

**Midtrans Production IPs:**
- 103.127.16.0/23
- 103.208.22.0/24

---

## MONITORING WEBHOOKS

### Check Payment Logs

Query the `payment_logs` table to see all webhook activity:

```sql
SELECT
    id,
    order_id,
    event_type,
    transaction_status,
    signature_valid,
    error_message,
    created_at
FROM payment_logs
WHERE order_id = 'INV-xxx'
ORDER BY created_at DESC;
```

### Check Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep -i midtrans
```

### Check Midtrans Dashboard

1. Login to Midtrans Dashboard
2. Go to **Transactions** menu
3. Find your transaction
4. Click to see details
5. Scroll to **Notification History** section
6. See all webhook attempts and responses

---

## PRODUCTION CHECKLIST

Before going live with production:

- [ ] Update .env with production credentials
- [ ] Set MIDTRANS_IS_PRODUCTION=true
- [ ] Configure Payment Notification URL in production dashboard
- [ ] Use HTTPS (not HTTP) for all URLs
- [ ] Valid SSL certificate installed
- [ ] Test complete payment flow with test cards
- [ ] Monitor webhook logs for 24 hours
- [ ] Set up alerts for failed webhooks
- [ ] Document emergency rollback procedure

---

## FREQUENTLY ASKED QUESTIONS

### Q: Can I use localhost for testing?

**A:** No. Midtrans servers cannot reach localhost. Options:
1. Use ngrok to expose localhost: `ngrok http 8000`
2. Deploy to staging server
3. Use services like localtunnel

### Q: Do I need to set redirect URLs?

**A:** Yes, but they're already configured in your dashboard. The redirect URLs are where users go after payment, while webhook URL is where Midtrans sends server-to-server notifications.

### Q: What if webhook fails?

**A:** Midtrans will retry multiple times (see Webhook Retry Mechanism above). You can also manually trigger webhook from dashboard.

### Q: How do I switch from sandbox to production?

**A:**
1. Get production credentials from Midtrans
2. Update .env with production keys
3. Set MIDTRANS_IS_PRODUCTION=true
4. Configure webhook URL in production dashboard

---

## ADDITIONAL RESOURCES

- Midtrans Documentation: https://docs.midtrans.com
- API Reference: https://api-docs.midtrans.com
- Support: support@midtrans.com
- Status Page: https://status.midtrans.com

---

**Last Updated:** 2025-11-02
**Configuration Status:** Sandbox Ready (After setting Payment Notification URL)
**Next Step:** Set Payment Notification URL in Midtrans Dashboard ⚠️
