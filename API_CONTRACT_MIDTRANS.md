# MIDTRANS API CONTRACT

Complete API specification for Midtrans payment integration.

Version: 2.0
Date: 2025-10-30

---

## BASE URL

```
Production: https://yourdomain.com/api
Development: http://localhost:8000/api
```

---

## AUTHENTICATION

All protected endpoints require Sanctum bearer token.

### Header Format

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## API ENDPOINTS

### 1. USER LOGIN

**Endpoint**: `/v1/login`
**Method**: `POST`
**Auth**: None

#### Purpose
Authenticate user and get bearer token for API access.

#### Request Payload

```json
{
  "email": "tas@gmail.com",
  "password": "123123"
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| email | string | Yes | Valid email format |
| password | string | Yes | Min 6 characters |

#### Success Response (200)

```json
{
  "token": "1|abc123def456ghi789jkl012mno345pqr678stu901vwx234yz",
  "user": {
    "id": 10,
    "name": "Test User",
    "email": "tas@gmail.com",
    "phone": "08123456789",
    "kode_pemesanan": "ORD-001"
  }
}
```

#### Error Response (401)

```json
{
  "message": "Invalid credentials"
}
```

#### Steps

1. Receive email and password
2. Validate input format
3. Check credentials against database
4. Generate Sanctum token if valid
5. Return token with user data

---

### 2. GET USER PROFILE

**Endpoint**: `/v1/user-profile`
**Method**: `GET`
**Auth**: Required (Sanctum)

#### Purpose
Get authenticated user profile with invitation and package details.

#### Request Headers

```
Authorization: Bearer {token}
Accept: application/json
```

#### Success Response (200)

```json
{
  "data": {
    "id": 10,
    "name": "Test User",
    "email": "tas@gmail.com",
    "phone": "08123456789",
    "kode_pemesanan": "ORD-001",
    "invitation": {
      "id": 5,
      "user_id": 10,
      "paket_undangan_id": 3,
      "status": "step4",
      "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
      "midtrans_transaction_id": "midtrans-tx-1234567890",
      "payment_status": "paid",
      "domain_expires_at": "2026-10-30T12:00:00.000000Z",
      "payment_confirmed_at": "2025-10-30T12:00:00.000000Z",
      "package_price_snapshot": "299000.00",
      "package_duration_snapshot": 12,
      "paket_undangan": {
        "id": 3,
        "name_paket": "Paket Platinum",
        "price": 299000,
        "masa_aktif": 12,
        "jenis_paket": "premium",
        "halaman_buku": "unlimited",
        "kirim_wa": "unlimited",
        "bebas_pilih_tema": true,
        "kirim_hadiah": true,
        "import_data": true
      }
    }
  }
}
```

#### Error Response (401)

```json
{
  "message": "Unauthenticated"
}
```

#### Steps

1. Verify bearer token
2. Get authenticated user ID
3. Load user with relationships (invitation, package)
4. Return complete profile data

---

### 3. GET AVAILABLE PACKAGES

**Endpoint**: `/v1/paket-undangan`
**Method**: `GET`
**Auth**: None (Public)

#### Purpose
Get list of all available wedding invitation packages with pricing.

#### Success Response (200)

```json
{
  "data": [
    {
      "id": 1,
      "name_paket": "Paket Basic",
      "price": 99000,
      "masa_aktif": 3,
      "jenis_paket": "basic",
      "halaman_buku": "50",
      "kirim_wa": "100",
      "bebas_pilih_tema": false,
      "kirim_hadiah": false,
      "import_data": false
    },
    {
      "id": 2,
      "name_paket": "Paket Gold",
      "price": 199000,
      "masa_aktif": 6,
      "jenis_paket": "standard",
      "halaman_buku": "200",
      "kirim_wa": "500",
      "bebas_pilih_tema": true,
      "kirim_hadiah": false,
      "import_data": true
    },
    {
      "id": 3,
      "name_paket": "Paket Platinum",
      "price": 299000,
      "masa_aktif": 12,
      "jenis_paket": "premium",
      "halaman_buku": "unlimited",
      "kirim_wa": "unlimited",
      "bebas_pilih_tema": true,
      "kirim_hadiah": true,
      "import_data": true
    }
  ]
}
```

#### Steps

1. Query all active packages from database
2. Return sorted by price ascending
3. No authentication required

---

### 4. CREATE SNAP TOKEN

**Endpoint**: `/midtrans/create-snap-token`
**Method**: `POST`
**Auth**: Required (Sanctum)

#### Purpose
Generate Midtrans Snap payment token for user's invitation.

#### Request Headers

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

#### Request Payload

```json
{
  "invitation_id": 5,
  "amount": 299000,
  "customer_details": {
    "first_name": "Test",
    "last_name": "User",
    "email": "tas@gmail.com",
    "phone": "08123456789"
  },
  "item_details": [
    {
      "id": "paket-3",
      "name": "Paket Platinum",
      "price": 299000,
      "quantity": 1
    }
  ]
}
```

#### Validation Rules

| Field | Type | Required | Rules |
|-------|------|----------|-------|
| invitation_id | integer | Yes | Exists in invitations, belongs to authenticated user |
| amount | numeric | Yes | Min: 10000, Max: 100000000 |
| customer_details | object | No | Optional customer info |
| customer_details.first_name | string | No | Max 100 chars |
| customer_details.last_name | string | No | Max 100 chars |
| customer_details.email | string | No | Valid email |
| customer_details.phone | string | No | Max 20 chars |
| item_details | array | No | Optional item details |
| item_details.*.id | string | No | Max 50 chars |
| item_details.*.name | string | No | Max 255 chars |
| item_details.*.price | numeric | No | Min 0 |
| item_details.*.quantity | integer | No | Min 1 |

#### Business Rules

1. Invitation must belong to authenticated user
2. Invitation payment_status must be "pending"
3. Invitation must not have existing order_id
4. Amount must match package price exactly
5. Order ID generated as UUID format (INV-{uuid})

#### Success Response (201)

```json
{
  "success": true,
  "data": {
    "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
    "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
    "gross_amount": 299000,
    "invitation_id": 5,
    "expires_at": "2025-10-31T12:00:00Z"
  },
  "message": "Snap token created successfully"
}
```

#### Error Response (422) - Validation Failed

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "This invitation has already been paid."
    ],
    "amount": [
      "Amount does not match package price."
    ]
  }
}
```

#### Error Response (422) - Already Initiated

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "Payment already initiated for this invitation."
    ]
  }
}
```

#### Error Response (503) - Midtrans Error

```json
{
  "success": false,
  "message": "Failed to generate payment token. Please try again later."
}
```

#### Steps

1. Authenticate user via Sanctum token
2. Validate request payload
3. Check invitation ownership
4. Verify invitation payment_status is "pending"
5. Verify invitation has no existing order_id
6. Validate amount matches package price
7. Generate UUID-based order_id
8. Build Midtrans transaction parameters
9. Call Midtrans API to generate Snap token
10. Save order_id to invitation in database transaction
11. Create payment_log entry for audit
12. Return snap_token and order details
13. Log all operations

#### Frontend Integration

Use snap_token with Midtrans Snap.js:

```javascript
snap.pay(snap_token, {
  onSuccess: function(result) {
    // Payment success
  },
  onPending: function(result) {
    // Payment pending
  },
  onError: function(result) {
    // Payment error
  }
});
```

---

### 5. WEBHOOK NOTIFICATION

**Endpoint**: `/v1/midtrans/webhook`
**Method**: `POST`
**Auth**: None (Signature verification)

#### Purpose
Receive payment status notification from Midtrans server.

#### Request Headers

```
Content-Type: application/json
Accept: application/json
```

#### Request Payload (from Midtrans)

```json
{
  "transaction_time": "2025-10-30 12:00:00",
  "transaction_status": "settlement",
  "transaction_id": "midtrans-tx-1234567890",
  "status_message": "midtrans payment success",
  "status_code": "200",
  "signature_key": "8f2d7c9e1b4a5f6d3c8e7a9b2d5f1e4c3b6a9d2e5f8a1c4d7b0e3f6a9c2d5e8f1",
  "settlement_time": "2025-10-30 12:01:00",
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

#### Signature Verification

Calculate signature:
```
SHA512(order_id + status_code + gross_amount + server_key)
```

Compare with `signature_key` from payload.

#### Transaction Status Mapping

| Midtrans Status | Action | Database Status |
|-----------------|--------|-----------------|
| capture | Mark as paid | paid |
| settlement | Mark as paid | paid |
| pending | Keep pending | pending |
| challenge | Keep pending (fraud check) | pending |
| deny | Mark as failed | failed |
| cancel | Mark as failed | failed |
| expire | Mark as failed | failed |
| refund | Mark as refunded | refunded |

#### Success Response (200)

```json
{
  "message": "Webhook processed successfully"
}
```

#### Response (200) - Idempotency

```json
{
  "message": "Already processed"
}
```

#### Error Response (403) - Invalid Signature

```json
{
  "message": "Invalid signature"
}
```

#### Error Response (404) - Order Not Found

```json
{
  "message": "Order not found"
}
```

#### Error Response (500) - Processing Failed

```json
{
  "message": "Webhook processing failed"
}
```

#### Steps

1. Log webhook receipt immediately
2. Extract order_id from payload
3. Find invitation by order_id
4. Return 404 if invitation not found
5. Get user_id from invitation
6. Load user-specific Midtrans config
7. Calculate signature: SHA512(order_id + status_code + gross_amount + server_key)
8. Compare calculated signature with received signature_key
9. Return 403 if signature invalid
10. Check idempotency: if already paid and status is settlement/capture, return success
11. Start database transaction
12. Map transaction_status to payment_status
13. Update invitation with:
    - payment_status
    - midtrans_transaction_id
    - payment_confirmed_at (if paid)
    - domain_expires_at (if paid, based on package duration)
14. Create payment_log entry for processed webhook
15. Commit transaction
16. Return 200 success
17. Log all operations

#### Security Features

1. Signature verification prevents unauthorized webhooks
2. Idempotency check prevents duplicate processing
3. Database transactions ensure data integrity
4. Complete audit trail in payment_logs
5. No authentication required (external callback)

---

## DATA MODELS

### Invitation

```json
{
  "id": 5,
  "user_id": 10,
  "paket_undangan_id": 3,
  "status": "step4",
  "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
  "midtrans_transaction_id": "midtrans-tx-1234567890",
  "payment_status": "paid",
  "domain_expires_at": "2026-10-30T12:00:00.000000Z",
  "payment_confirmed_at": "2025-10-30T12:00:00.000000Z",
  "package_price_snapshot": "299000.00",
  "package_duration_snapshot": 12,
  "package_features_snapshot": {
    "jenis_paket": "premium",
    "name_paket": "Paket Platinum",
    "halaman_buku": "unlimited",
    "kirim_wa": "unlimited",
    "bebas_pilih_tema": true,
    "kirim_hadiah": true,
    "import_data": true,
    "snapshot_at": "2025-10-30T10:00:00Z"
  },
  "created_at": "2025-10-01T10:00:00.000000Z",
  "updated_at": "2025-10-30T12:00:00.000000Z"
}
```

### Payment Status Enum

```
- pending
- paid
- failed
- refunded
```

---

## PAYMENT FLOW SEQUENCE

```
1. User registers/logs in
   ├─> POST /v1/login
   └─> Get bearer token

2. User creates invitation (separate flow)
   ├─> POST /v1/one-step
   ├─> POST /v1/two-step
   ├─> POST /v1/three-step
   └─> POST /v1/for-step

3. User views profile with package details
   ├─> GET /v1/user-profile
   └─> See: Paket Platinum - Rp 299,000 - status: pending

4. User initiates payment
   ├─> POST /midtrans/create-snap-token
   │   └─> Payload: invitation_id, amount
   ├─> Backend validates amount vs package price
   ├─> Backend generates order_id (UUID)
   ├─> Backend calls Midtrans API
   ├─> Backend saves order_id to invitation
   └─> Response: snap_token, order_id

5. User completes payment in Midtrans
   ├─> Frontend loads Midtrans Snap UI with snap_token
   ├─> User selects payment method
   ├─> User completes payment
   └─> Midtrans processes payment

6. Midtrans sends webhook notification
   ├─> POST /v1/midtrans/webhook
   ├─> Backend verifies signature
   ├─> Backend checks transaction_status
   ├─> Backend updates payment_status to "paid"
   ├─> Backend sets payment_confirmed_at timestamp
   ├─> Backend calculates domain_expires_at (+12 months)
   └─> Response: 200 OK

7. User sees updated status
   ├─> GET /v1/user-profile
   └─> payment_status: "paid", domain expires: 2026-10-30
```

---

## ERROR CODES

| Status | Meaning | When |
|--------|---------|------|
| 200 | Success | Request processed successfully |
| 201 | Created | Snap token created |
| 401 | Unauthorized | Invalid/missing bearer token |
| 403 | Forbidden | Invalid webhook signature |
| 404 | Not Found | Order not found in webhook |
| 422 | Validation Error | Invalid input data |
| 500 | Internal Error | Unexpected server error |
| 503 | Service Unavailable | Midtrans API error |

---

## VALIDATION ERRORS

### Invalid Amount

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": [
      "Amount does not match package price."
    ]
  }
}
```

### Already Paid

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "This invitation has already been paid."
    ]
  }
}
```

### Payment Already Initiated

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "Payment already initiated for this invitation."
    ]
  }
}
```

### Invalid Invitation

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "Invalid invitation or you do not have permission to access this invitation."
    ]
  }
}
```

---

## RATE LIMITING

All API endpoints protected by Laravel throttle middleware.

Default limits:
- Public endpoints: 60 requests/minute
- Authenticated endpoints: 120 requests/minute
- Webhook endpoint: No limit (external service)

---

## TESTING

### Sandbox Credentials

```
Server Key: SB-Mid-server-xxxxxxxxxxxxx
Client Key: SB-Mid-client-xxxxxxxxxxxxx
```

### Test Card Numbers

**Success:**
- Card: 4811 1111 1111 1114
- CVV: 123
- Expiry: 01/25
- OTP: 112233

**Failure:**
- Card: 4911 1111 1111 1113
- CVV: 123
- Expiry: 01/25

---

## ENVIRONMENT VARIABLES

Required .env configuration:

```env
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_SANITIZED=true
MIDTRANS_IS_3DS=true

MIDTRANS_FINISH_URL=https://your-frontend.com/payment/success
MIDTRANS_ERROR_URL=https://your-frontend.com/payment/error
MIDTRANS_PENDING_URL=https://your-frontend.com/payment/pending

MIDTRANS_MIN_AMOUNT=10000
MIDTRANS_MAX_AMOUNT=100000000
MIDTRANS_TOKEN_EXPIRY_HOURS=24
```

---

## CHANGELOG

### Version 2.0 (2025-10-30)

**Breaking Changes:**
- Request payload structure changed
- Response structure standardized
- Validation rules added

**New Features:**
- UUID-based order IDs
- Multi-tenant config support
- Payment status: refunded
- Idempotency check
- Comprehensive audit logging

**Improvements:**
- Amount validation vs package price
- Signature verification with timing-safe comparison
- Domain expiry auto-calculation
- Error response consistency

**Bug Fixes:**
- Fixed null pointer in config loading
- Fixed wrong callback URLs
- Fixed missing transaction statuses
- Fixed race conditions in webhook

---

## SUPPORT

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Check payment logs: `SELECT * FROM payment_logs ORDER BY created_at DESC LIMIT 10`
- Verify environment configuration
- Test signature calculation
- Review webhook payload structure

---

**Document Version**: 2.0
**Last Updated**: 2025-10-30
**Status**: Production Ready
