# Midtrans Payment Gateway API Flow Documentation

## Overview

This application integrates Midtrans payment gateway with two primary endpoints:

1. POST `/midtrans/create-snap-token` - Generate payment token
2. POST `/v1/midtrans/webhook` - Handle payment notifications

---

## Architecture Components

### 1. Controller Layer
- `MidtransController.php` - Handles HTTP requests and responses
- Dependency injection: `MidtransService`

### 2. Service Layer
- `MidtransService.php` - Business logic for Midtrans API integration
- Manages configuration and token generation

### 3. Model Layer
- `MidtransTransaction.php` - Stores Midtrans credentials per user
- `Invitation.php` - Stores order and payment status

### 4. Configuration
- `config/midtrans.php` - Default Midtrans settings
- `.env` - Environment-specific credentials

---

## API Endpoint 1: Create Snap Token

### Route Definition
```php
POST /midtrans/create-snap-token
Name: midtrans.createSnapToken
```

### Purpose
Generate a Snap token from Midtrans for client-side payment popup.

### Authentication
Requires authenticated user via Sanctum middleware (implicit from auth context).

### Request Flow

```
Client Request
    ↓
MidtransController::createSnapToken()
    ↓
1. Get authenticated user (Auth::user())
    ↓
2. Generate unique order_id (INV-{timestamp})
    ↓
3. Prepare transaction parameters:
   - transaction_details (order_id, gross_amount)
   - customer_details (name, email)
   - callbacks (finish URL)
    ↓
4. Call MidtransService::createTransaction($params)
    ↓
5. MidtransService fetches config from DB or .env
    ↓
6. Set Midtrans SDK configuration:
   - Config::$serverKey
   - Config::$isProduction
   - Config::$isSanitized = true
   - Config::$is3ds = true
    ↓
7. Call Midtrans SDK: Snap::getSnapToken($params)
    ↓
8. Midtrans API returns snap_token
    ↓
9. Return JSON response with snap_token and order_id
    ↓
Client receives token for payment UI
```

### Request Example
```bash
POST /api/midtrans/create-snap-token
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
  "amount": 150000
}
```

### Response Example
```json
{
  "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
  "order_id": "INV-1698765432"
}
```

### Key Parameters

**Input:**
- `amount` (optional, default: 50000) - Transaction amount in IDR

**Output:**
- `snap_token` - Token for Midtrans Snap popup
- `order_id` - Unique order identifier

### Configuration Loading Logic

```php
// 1. Try to get config from database (latest record)
$config = MidtransTransaction::latest()->first();

// 2. Determine environment (production/sandbox)
$isProduction = $config 
    ? $config->metode_production === 'production' 
    : config('midtrans.is_production');

// 3. Get API keys
$serverKey = $config->server_key ?? config('midtrans.server_key');
$clientKey = $config->client_key ?? config('midtrans.client_key');
```

This allows dynamic configuration per user or falls back to .env values.

### Security Considerations

**Current Issues:**
1. No validation on request input (amount)
2. No transaction record saved before token generation
3. Order ID uses timestamp (predictable, not cryptographically secure)
4. No rate limiting implemented
5. Missing CSRF protection (API endpoint)

**Recommended Fixes:**
1. Add FormRequest validation for amount
2. Save transaction record with pending status
3. Use UUID or secure random for order_id
4. Implement throttle middleware
5. Add API token authentication check

---

## API Endpoint 2: Handle Webhook

### Route Definition
```php
POST /v1/midtrans/webhook
No route name defined
```

### Purpose
Receive payment status notifications from Midtrans server.

### Authentication
No authentication required (webhook from external service).

### Request Flow

```
Midtrans Server Sends Notification
    ↓
MidtransController::handleWebhook()
    ↓
1. Receive webhook payload:
   - order_id
   - status_code
   - gross_amount
   - signature_key
   - transaction_status
    ↓
2. Verify signature:
   SHA512(order_id + status_code + gross_amount + server_key)
    ↓
3. Compare with signature_key from Midtrans
    ↓
4. If invalid → Return 403 Forbidden
    ↓
5. If valid → Find Invitation by order_id
    ↓
6. Check transaction_status:
   - 'capture' or 'settlement' → payment_status = 'paid'
   - 'deny', 'cancel', 'expire' → payment_status = 'failed'
    ↓
7. Update invitation record:
   - payment_status
   - payment_confirmed_at (if paid)
    ↓
8. Return 200 OK with success message
    ↓
Midtrans marks webhook as processed
```

### Webhook Payload Example
```json
{
  "transaction_time": "2024-10-28 10:30:00",
  "transaction_status": "settlement",
  "transaction_id": "abc123-def456",
  "status_code": "200",
  "signature_key": "8f2d7c9e1b4a5f6d3c8e7a9b2d5f1e4c...",
  "settlement_time": "2024-10-28 10:31:00",
  "payment_type": "credit_card",
  "order_id": "INV-1698765432",
  "merchant_id": "G123456789",
  "gross_amount": "150000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
```

### Response Example
```json
{
  "message": "Webhook processed"
}
```

### Signature Verification Logic

```php
$serverKey = config('midtrans.server_key');
$signatureKey = hash('sha512',
    $request->order_id .
    $request->status_code .
    $request->gross_amount .
    $serverKey
);

if ($signatureKey !== $request->signature_key) {
    return response()->json(['message' => 'Invalid signature'], 403);
}
```

This prevents unauthorized webhook calls.

### Transaction Status Mapping

| Midtrans Status | Action | Database Status |
|-----------------|--------|-----------------|
| `capture` | Mark as paid | `paid` |
| `settlement` | Mark as paid | `paid` |
| `pending` | No action | (unchanged) |
| `deny` | Mark as failed | `failed` |
| `cancel` | Mark as failed | `failed` |
| `expire` | Mark as failed | `failed` |

### Security Considerations

**Current Implementation:**
1. Signature verification implemented (good)
2. No CSRF token needed (external webhook)
3. Uses server_key from config

**Issues:**
1. No logging of webhook attempts
2. No idempotency check (duplicate webhooks)
3. No validation of request structure
4. No rate limiting on webhook endpoint
5. Hardcoded invitation model (tight coupling)

**Recommended Fixes:**
1. Log all webhook payloads for audit trail
2. Add idempotency key check to prevent duplicate processing
3. Validate webhook payload structure with FormRequest
4. Implement IP whitelist for Midtrans servers
5. Extract business logic to service layer
6. Add database transaction for atomic updates

---

## Database Schema

### midtrans_transactions Table

```sql
CREATE TABLE midtrans_transactions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(255) NOT NULL,
    server_key VARCHAR(255) NOT NULL,
    client_key VARCHAR(255) NOT NULL,
    metode_production VARCHAR(255) NOT NULL,
    methode_pembayaran VARCHAR(255) NOT NULL,
    id_methode_pembayaran VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

**Purpose:** Store Midtrans credentials per user for multi-tenant setup.

**Fields:**
- `user_id` - Owner of the credentials
- `url` - Midtrans API endpoint URL
- `server_key` - Server-side API key
- `client_key` - Client-side API key
- `metode_production` - Environment: 'production' or 'sandbox'
- `methode_pembayaran` - Payment method type
- `id_methode_pembayaran` - Payment method identifier

**Issue:** Field naming inconsistency (`methode` should be `metode`).

### invitations Table (Relevant Fields)

```sql
-- Assumed structure based on code
ALTER TABLE invitations ADD COLUMN order_id VARCHAR(255);
ALTER TABLE invitations ADD COLUMN payment_status VARCHAR(50);
ALTER TABLE invitations ADD COLUMN payment_confirmed_at TIMESTAMP NULL;
```

**Note:** Migration file not provided, inferred from code usage.

---

## Configuration Files

### config/midtrans.php

```php
return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', true),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => env('MIDTRANS_IS_3DS', true),
];
```

### Required .env Variables

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true
```

**Production Keys:**
- Server Key: `Mid-server-xxxxxxxxxxxxx`
- Client Key: `Mid-client-xxxxxxxxxxxxx`

**Sandbox Keys:**
- Server Key: `SB-Mid-server-xxxxxxxxxxxxx`
- Client Key: `SB-Mid-client-xxxxxxxxxxxxx`

---

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     PAYMENT INITIATION FLOW                      │
└─────────────────────────────────────────────────────────────────┘

1. User selects package/invitation
        ↓
2. Frontend calls POST /midtrans/create-snap-token
   Payload: { "amount": 150000 }
        ↓
3. Backend generates order_id (INV-{timestamp})
        ↓
4. MidtransService loads config (DB or .env)
        ↓
5. Call Midtrans API: Snap::getSnapToken()
        ↓
6. Midtrans returns snap_token
        ↓
7. Backend responds: { "snap_token": "...", "order_id": "..." }
        ↓
8. Frontend loads Midtrans Snap UI with token
        ↓
9. User completes payment in Midtrans popup


┌─────────────────────────────────────────────────────────────────┐
│                     WEBHOOK NOTIFICATION FLOW                    │
└─────────────────────────────────────────────────────────────────┘

1. Midtrans processes payment
        ↓
2. Midtrans sends webhook to POST /v1/midtrans/webhook
   Payload: {
     "order_id": "INV-...",
     "transaction_status": "settlement",
     "signature_key": "...",
     ...
   }
        ↓
3. Backend verifies signature (SHA512 hash)
        ↓
4. If invalid → Return 403 Forbidden
        ↓
5. If valid → Find invitation by order_id
        ↓
6. Update payment status based on transaction_status:
   - settlement/capture → paid
   - deny/cancel/expire → failed
        ↓
7. Return 200 OK to Midtrans
        ↓
8. Midtrans marks webhook as delivered
        ↓
9. User sees updated payment status in app
```

---

## Testing Guide

### Test Create Snap Token

```bash
# 1. Login and get Sanctum token
POST /api/login
{
  "email": "user@example.com",
  "password": "password"
}

# Response: { "token": "1|xxxxx..." }

# 2. Create snap token
curl -X POST http://localhost:8000/api/midtrans/create-snap-token \
  -H "Authorization: Bearer 1|xxxxx..." \
  -H "Content-Type: application/json" \
  -d '{"amount": 100000}'

# Expected response:
{
  "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
  "order_id": "INV-1698765432"
}
```

### Test Webhook Locally

```bash
# Simulate Midtrans webhook
curl -X POST http://localhost:8000/api/v1/midtrans/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "INV-1698765432",
    "status_code": "200",
    "gross_amount": "100000.00",
    "signature_key": "CALCULATED_HASH",
    "transaction_status": "settlement"
  }'
```

**Calculate signature_key:**
```php
$serverKey = config('midtrans.server_key');
$signature = hash('sha512', 'INV-1698765432' . '200' . '100000.00' . $serverKey);
```

### Midtrans Sandbox Test Cards

**Successful Payment:**
- Card Number: `4811 1111 1111 1114`
- CVV: `123`
- Expiry: `01/25`
- OTP: `112233`

**Failed Payment:**
- Card Number: `4911 1111 1111 1113`
- CVV: `123`
- Expiry: `01/25`

---

## Security Recommendations

### High Priority

1. Add validation to createSnapToken:
```php
$validated = $request->validate([
    'amount' => 'required|integer|min:10000|max:100000000',
    'order_description' => 'nullable|string|max:255'
]);
```

2. Save transaction before generating token:
```php
$transaction = MidtransTransaction::create([
    'user_id' => $user->id,
    'order_id' => $orderId,
    'amount' => $grossAmount,
    'status' => 'pending'
]);
```

3. Use UUID for order_id:
```php
use Illuminate\Support\Str;
$orderId = 'INV-' . Str::uuid();
```

4. Add webhook payload validation:
```php
$validated = $request->validate([
    'order_id' => 'required|string',
    'transaction_status' => 'required|string',
    'signature_key' => 'required|string',
    'gross_amount' => 'required|numeric'
]);
```

5. Implement idempotency check:
```php
if ($invitation->payment_status === 'paid') {
    return response()->json(['message' => 'Already processed'], 200);
}
```

### Medium Priority

6. Add webhook logging:
```php
Log::channel('midtrans')->info('Webhook received', $request->all());
```

7. IP whitelist middleware for webhook:
```php
// Midtrans IPs: https://docs.midtrans.com/en/after-payment/http-notification
$allowedIps = ['103.127.16.0/23', '103.208.22.0/24'];
```

8. Extract business logic to service:
```php
class PaymentService {
    public function processWebhook($data) {
        // Business logic here
    }
}
```

9. Add database transaction for atomic updates:
```php
DB::transaction(function() use ($invitation, $request) {
    $invitation->update([...]);
    PaymentLog::create([...]);
});
```

10. Rate limiting on create token:
```php
Route::post('/midtrans/create-snap-token', ...)
    ->middleware('throttle:10,1'); // 10 requests per minute
```

---

## Error Handling

### Current Issues

1. No try-catch blocks in controller methods
2. Midtrans API errors not handled
3. Database errors not caught
4. No user-friendly error messages

### Recommended Implementation

```php
public function createSnapToken(Request $request)
{
    try {
        $validated = $request->validate([...]);
        
        $snapToken = $this->midtransService->createTransaction($params);
        
        return response()->json([
            'snap_token' => $snapToken,
            'order_id' => $orderId
        ], 201);
        
    } catch (ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
        
    } catch (MidtransException $e) {
        Log::error('Midtrans API error: ' . $e->getMessage());
        return response()->json([
            'message' => 'Payment gateway error',
            'error' => $e->getMessage()
        ], 503);
        
    } catch (\Exception $e) {
        Log::error('Snap token creation failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Internal server error'
        ], 500);
    }
}
```

---

## Performance Considerations

### Current Performance Issues

1. No caching of Midtrans configuration
2. Database query in every token generation
3. No connection pooling for Midtrans API
4. Synchronous webhook processing

### Optimization Recommendations

1. Cache Midtrans config:
```php
$config = Cache::remember('midtrans_config', 3600, function() {
    return MidtransTransaction::latest()->first();
});
```

2. Queue webhook processing:
```php
dispatch(new ProcessMidtransWebhook($request->all()));
```

3. Add database indexes:
```sql
ALTER TABLE invitations ADD INDEX idx_order_id (order_id);
ALTER TABLE invitations ADD INDEX idx_payment_status (payment_status);
```

4. Use eager loading if needed:
```php
$invitation = Invitation::with('user', 'package')->where(...)->first();
```

---

## Monitoring and Logging

### Recommended Log Channels

```php
// config/logging.php
'channels' => [
    'midtrans' => [
        'driver' => 'daily',
        'path' => storage_path('logs/midtrans.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### What to Log

1. Token generation attempts
2. Webhook received payloads
3. Signature verification failures
4. Payment status updates
5. API errors from Midtrans

### Example Implementation

```php
Log::channel('midtrans')->info('Snap token created', [
    'user_id' => $user->id,
    'order_id' => $orderId,
    'amount' => $grossAmount
]);

Log::channel('midtrans')->warning('Invalid webhook signature', [
    'order_id' => $request->order_id,
    'ip' => $request->ip()
]);
```

---

## Summary

The Midtrans integration provides basic payment functionality with two endpoints:

1. Create Snap Token - Generates payment UI token
2. Handle Webhook - Processes payment notifications

Current implementation works for basic scenarios but needs improvements in:

- Input validation
- Error handling
- Security (idempotency, rate limiting)
- Logging and monitoring
- Performance optimization
- Code organization (service layer extraction)

Follow the recommendations in this document to production-ready the implementation.
