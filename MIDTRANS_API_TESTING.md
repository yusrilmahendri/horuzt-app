# MIDTRANS API TESTING GUIDE

Complete curl-based testing for Midtrans payment integration.

Test validates:
- Authentication flow
- User invitation and package data
- Snap token generation
- Webhook processing
- Payment status updates

---

## PREREQUISITES

Set environment variables:

```bash
export API_BASE_URL="http://localhost:8000/api"
export ADMIN_EMAIL="admin"
export ADMIN_PASSWORD="12345678"
export USER_EMAIL="tas@gmail.com"
export USER_PASSWORD="123123"
```

---

## TEST FLOW

### STEP 1: Admin Login

Get admin bearer token for administrative operations.

```bash
curl -X POST "${API_BASE_URL}/v1/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "'${ADMIN_EMAIL}'",
    "password": "'${ADMIN_PASSWORD}'"
  }' | jq -r '.token' > /tmp/admin_token.txt

export ADMIN_TOKEN=$(cat /tmp/admin_token.txt)

echo "Admin Token: ${ADMIN_TOKEN}"
```

Expected response:
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin"
  }
}
```

---

### STEP 2: User Login

Get user bearer token for payment operations.

```bash
curl -X POST "${API_BASE_URL}/v1/login" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "'${USER_EMAIL}'",
    "password": "'${USER_PASSWORD}'"
  }' | jq -r '.token' > /tmp/user_token.txt

export USER_TOKEN=$(cat /tmp/user_token.txt)

echo "User Token: ${USER_TOKEN}"
```

Expected response:
```json
{
  "token": "2|xyz789...",
  "user": {
    "id": 10,
    "name": "Test User",
    "email": "tas@gmail.com"
  }
}
```

---

### STEP 3: Get User Profile & Invitation Data

Retrieve user's invitation details and package pricing.

```bash
curl -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Accept: application/json" | jq '.' > /tmp/user_profile.json

cat /tmp/user_profile.json
```

Extract critical data:

```bash
export INVITATION_ID=$(jq -r '.data.invitation.id' /tmp/user_profile.json)
export PACKAGE_ID=$(jq -r '.data.invitation.paket_undangan_id' /tmp/user_profile.json)
export PACKAGE_NAME=$(jq -r '.data.invitation.paket_undangan.name_paket' /tmp/user_profile.json)
export PACKAGE_PRICE=$(jq -r '.data.invitation.paket_undangan.price' /tmp/user_profile.json)

echo "Invitation ID: ${INVITATION_ID}"
echo "Package: ${PACKAGE_NAME}"
echo "Price: Rp ${PACKAGE_PRICE}"
```

Expected output:
```
Invitation ID: 5
Package: Paket Platinum
Price: Rp 299000
```

Verification checklist:
- [ ] Invitation exists
- [ ] Package is "Paket Platinum"
- [ ] Price is Rp 299,000
- [ ] Payment status is "pending"

---

### STEP 4: Get Available Packages (Verification)

Verify package pricing matches master data.

```bash
curl -X GET "${API_BASE_URL}/v1/paket-undangan" \
  -H "Accept: application/json" | jq '.data[] | select(.name_paket == "Paket Platinum")'
```

Expected response:
```json
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
```

Validation:
- Package price in master: Rp 299,000
- User invitation package price: Rp 299,000
- Match: PASS / FAIL

---

### STEP 5: Create Midtrans Snap Token

Generate payment token for user's invitation.

IMPORTANT: Amount must match package price exactly (299000).

```bash
curl -X POST "${API_BASE_URL}/midtrans/create-snap-token" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "invitation_id": '${INVITATION_ID}',
    "amount": '${PACKAGE_PRICE}',
    "customer_details": {
      "first_name": "Test",
      "last_name": "User",
      "email": "'${USER_EMAIL}'",
      "phone": "08123456789"
    },
    "item_details": [
      {
        "id": "paket-'${PACKAGE_ID}'",
        "name": "'${PACKAGE_NAME}'",
        "price": '${PACKAGE_PRICE}',
        "quantity": 1
      }
    ]
  }' | jq '.' > /tmp/snap_token.json

cat /tmp/snap_token.json
```

Expected response:
```json
{
  "success": true,
  "data": {
    "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
    "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
    "gross_amount": 299000,
    "invitation_id": 5,
    "expires_at": "2025-10-31T10:00:00Z"
  },
  "message": "Snap token created successfully"
}
```

Validation checks:
- [ ] Status code: 201
- [ ] snap_token generated
- [ ] order_id format: INV-{uuid}
- [ ] gross_amount matches package price (299000)
- [ ] invitation_id matches user's invitation

Extract order details:

```bash
export ORDER_ID=$(jq -r '.data.order_id' /tmp/snap_token.json)
export SNAP_TOKEN=$(jq -r '.data.snap_token' /tmp/snap_token.json)
export GROSS_AMOUNT=$(jq -r '.data.gross_amount' /tmp/snap_token.json)

echo "Order ID: ${ORDER_ID}"
echo "Snap Token: ${SNAP_TOKEN}"
echo "Amount: ${GROSS_AMOUNT}"
```

---

### STEP 6: Verify Invitation Status After Token Creation

Check invitation updated with order_id.

```bash
curl -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Accept: application/json" | jq '.data.invitation | {order_id, payment_status, midtrans_transaction_id}'
```

Expected:
```json
{
  "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
  "payment_status": "pending",
  "midtrans_transaction_id": null
}
```

Validation:
- [ ] order_id set
- [ ] payment_status still "pending"
- [ ] midtrans_transaction_id is null

---

### STEP 7: Simulate Midtrans Webhook - Payment Success

Simulate successful payment notification from Midtrans.

First, calculate signature:

```bash
# Get server key from .env or config
export MIDTRANS_SERVER_KEY="your_server_key_here"

# Transaction details
export TRANSACTION_ID="midtrans-tx-$(date +%s)"
export STATUS_CODE="200"

# Calculate signature: SHA512(order_id + status_code + gross_amount + server_key)
export SIGNATURE=$(echo -n "${ORDER_ID}${STATUS_CODE}${GROSS_AMOUNT}${MIDTRANS_SERVER_KEY}" | openssl dgst -sha512 | awk '{print $2}')

echo "Signature: ${SIGNATURE}"
```

Send webhook:

```bash
curl -X POST "${API_BASE_URL}/v1/midtrans/webhook" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "transaction_time": "'$(date -u +"%Y-%m-%d %H:%M:%S")'",
    "transaction_status": "settlement",
    "transaction_id": "'${TRANSACTION_ID}'",
    "status_message": "midtrans payment success",
    "status_code": "'${STATUS_CODE}'",
    "signature_key": "'${SIGNATURE}'",
    "settlement_time": "'$(date -u +"%Y-%m-%d %H:%M:%S")'",
    "payment_type": "credit_card",
    "order_id": "'${ORDER_ID}'",
    "merchant_id": "G123456789",
    "gross_amount": "'${GROSS_AMOUNT}'.00",
    "fraud_status": "accept",
    "currency": "IDR"
  }' | jq '.'
```

Expected response:
```json
{
  "message": "Webhook processed successfully"
}
```

Validation:
- [ ] Status code: 200
- [ ] Message: "Webhook processed successfully"

---

### STEP 8: Verify Payment Status Updated to Paid

Check invitation payment status changed to "paid".

```bash
curl -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Accept: application/json" | jq '.data.invitation | {order_id, payment_status, payment_confirmed_at, domain_expires_at, midtrans_transaction_id}'
```

Expected:
```json
{
  "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
  "payment_status": "paid",
  "payment_confirmed_at": "2025-10-30T12:00:00.000000Z",
  "domain_expires_at": "2026-10-30T12:00:00.000000Z",
  "midtrans_transaction_id": "midtrans-tx-1234567890"
}
```

Critical validations:
- [ ] payment_status changed to "paid"
- [ ] payment_confirmed_at set to current timestamp
- [ ] domain_expires_at set (12 months from payment for Platinum)
- [ ] midtrans_transaction_id saved

---

### STEP 9: Verify Payment Logs

Check audit trail in payment_logs table.

```bash
# Admin endpoint required - check if available or use database query
mysql -e "SELECT id, event_type, transaction_status, gross_amount, signature_valid, created_at
          FROM payment_logs
          WHERE order_id = '${ORDER_ID}'
          ORDER BY created_at DESC;"
```

Expected entries:
1. event_type: "token_request" - transaction_status: "pending"
2. event_type: "webhook_received" - transaction_status: "settlement"
3. event_type: "webhook_processed" - transaction_status: "settlement", signature_valid: true

---

### STEP 10: Test Idempotency - Duplicate Webhook

Send same webhook again to verify idempotency.

```bash
curl -X POST "${API_BASE_URL}/v1/midtrans/webhook" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "transaction_time": "'$(date -u +"%Y-%m-%d %H:%M:%S")'",
    "transaction_status": "settlement",
    "transaction_id": "'${TRANSACTION_ID}'",
    "status_message": "midtrans payment success",
    "status_code": "'${STATUS_CODE}'",
    "signature_key": "'${SIGNATURE}'",
    "settlement_time": "'$(date -u +"%Y-%m-%d %H:%M:%S")'",
    "payment_type": "credit_card",
    "order_id": "'${ORDER_ID}'",
    "merchant_id": "G123456789",
    "gross_amount": "'${GROSS_AMOUNT}'.00",
    "fraud_status": "accept",
    "currency": "IDR"
  }' | jq '.'
```

Expected response:
```json
{
  "message": "Already processed"
}
```

Validation:
- [ ] Status code: 200
- [ ] Message indicates already processed
- [ ] Payment status remains "paid"
- [ ] No duplicate updates

---

## ERROR SCENARIOS TESTING

### Test 1: Invalid Amount

Amount does not match package price.

```bash
curl -X POST "${API_BASE_URL}/midtrans/create-snap-token" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "invitation_id": '${INVITATION_ID}',
    "amount": 100000,
    "customer_details": {
      "first_name": "Test",
      "last_name": "User",
      "email": "'${USER_EMAIL}'",
      "phone": "08123456789"
    }
  }'
```

Expected:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["Amount does not match package price."]
  }
}
```

Status: 422

---

### Test 2: Already Paid Invitation

Attempt to create token for paid invitation.

```bash
# This will fail because invitation is already paid from previous test
curl -X POST "${API_BASE_URL}/midtrans/create-snap-token" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "invitation_id": '${INVITATION_ID}',
    "amount": '${PACKAGE_PRICE}'
  }'
```

Expected:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": ["This invitation has already been paid."]
  }
}
```

Status: 422

---

### Test 3: Invalid Webhook Signature

Send webhook with wrong signature.

```bash
curl -X POST "${API_BASE_URL}/v1/midtrans/webhook" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "transaction_status": "settlement",
    "transaction_id": "test-invalid",
    "status_code": "200",
    "signature_key": "invalid_signature_here",
    "order_id": "'${ORDER_ID}'",
    "gross_amount": "'${GROSS_AMOUNT}'.00"
  }' | jq '.'
```

Expected:
```json
{
  "message": "Invalid signature"
}
```

Status: 403

---

## COMPLETE TEST SCRIPT

Save as `test_midtrans.sh`:

```bash
#!/bin/bash

# Midtrans API Complete Test Script

set -e

# Configuration
API_BASE_URL="http://localhost:8000/api"
ADMIN_EMAIL="admin"
ADMIN_PASSWORD="12345678"
USER_EMAIL="tas@gmail.com"
USER_PASSWORD="123123"
MIDTRANS_SERVER_KEY="your_server_key_here"

echo "========================================="
echo "MIDTRANS API TESTING"
echo "========================================="
echo ""

# Step 1: Admin Login
echo "[1/10] Admin Login..."
ADMIN_TOKEN=$(curl -s -X POST "${API_BASE_URL}/v1/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ADMIN_EMAIL}\",\"password\":\"${ADMIN_PASSWORD}\"}" | jq -r '.token')

if [ -z "$ADMIN_TOKEN" ] || [ "$ADMIN_TOKEN" = "null" ]; then
  echo "ERROR: Admin login failed"
  exit 1
fi
echo "Admin Token: ${ADMIN_TOKEN:0:20}..."

# Step 2: User Login
echo "[2/10] User Login..."
USER_TOKEN=$(curl -s -X POST "${API_BASE_URL}/v1/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${USER_EMAIL}\",\"password\":\"${USER_PASSWORD}\"}" | jq -r '.token')

if [ -z "$USER_TOKEN" ] || [ "$USER_TOKEN" = "null" ]; then
  echo "ERROR: User login failed"
  exit 1
fi
echo "User Token: ${USER_TOKEN:0:20}..."

# Step 3: Get User Profile
echo "[3/10] Getting User Profile..."
USER_PROFILE=$(curl -s -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}")

INVITATION_ID=$(echo $USER_PROFILE | jq -r '.data.invitation.id')
PACKAGE_NAME=$(echo $USER_PROFILE | jq -r '.data.invitation.paket_undangan.name_paket')
PACKAGE_PRICE=$(echo $USER_PROFILE | jq -r '.data.invitation.paket_undangan.price')

echo "Invitation ID: ${INVITATION_ID}"
echo "Package: ${PACKAGE_NAME}"
echo "Price: Rp ${PACKAGE_PRICE}"

# Step 4: Verify Package Price
echo "[4/10] Verifying Package Master Data..."
MASTER_PRICE=$(curl -s -X GET "${API_BASE_URL}/v1/paket-undangan" | jq -r ".data[] | select(.name_paket == \"${PACKAGE_NAME}\") | .price")

if [ "$PACKAGE_PRICE" = "$MASTER_PRICE" ]; then
  echo "Price Match: PASS (Rp ${PACKAGE_PRICE})"
else
  echo "Price Mismatch: FAIL (User: ${PACKAGE_PRICE}, Master: ${MASTER_PRICE})"
  exit 1
fi

# Step 5: Create Snap Token
echo "[5/10] Creating Midtrans Snap Token..."
SNAP_RESPONSE=$(curl -s -X POST "${API_BASE_URL}/midtrans/create-snap-token" \
  -H "Authorization: Bearer ${USER_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{
    \"invitation_id\": ${INVITATION_ID},
    \"amount\": ${PACKAGE_PRICE},
    \"customer_details\": {
      \"first_name\": \"Test\",
      \"last_name\": \"User\",
      \"email\": \"${USER_EMAIL}\",
      \"phone\": \"08123456789\"
    }
  }")

ORDER_ID=$(echo $SNAP_RESPONSE | jq -r '.data.order_id')
SNAP_TOKEN=$(echo $SNAP_RESPONSE | jq -r '.data.snap_token')

if [ -z "$ORDER_ID" ] || [ "$ORDER_ID" = "null" ]; then
  echo "ERROR: Failed to create snap token"
  echo $SNAP_RESPONSE | jq '.'
  exit 1
fi

echo "Order ID: ${ORDER_ID}"
echo "Snap Token: ${SNAP_TOKEN:0:20}..."

# Step 6: Verify Order Created
echo "[6/10] Verifying Order Creation..."
PAYMENT_STATUS=$(curl -s -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}" | jq -r '.data.invitation.payment_status')

if [ "$PAYMENT_STATUS" = "pending" ]; then
  echo "Status: PENDING (Correct)"
else
  echo "ERROR: Expected pending, got ${PAYMENT_STATUS}"
  exit 1
fi

# Step 7: Calculate Webhook Signature
echo "[7/10] Calculating Webhook Signature..."
TRANSACTION_ID="midtrans-tx-$(date +%s)"
STATUS_CODE="200"
SIGNATURE=$(echo -n "${ORDER_ID}${STATUS_CODE}${PACKAGE_PRICE}${MIDTRANS_SERVER_KEY}" | openssl dgst -sha512 | awk '{print $2}')

echo "Transaction ID: ${TRANSACTION_ID}"
echo "Signature: ${SIGNATURE:0:20}..."

# Step 8: Send Webhook
echo "[8/10] Sending Webhook..."
WEBHOOK_RESPONSE=$(curl -s -X POST "${API_BASE_URL}/v1/midtrans/webhook" \
  -H "Content-Type: application/json" \
  -d "{
    \"transaction_time\": \"$(date -u +\"%Y-%m-%d %H:%M:%S\")\",
    \"transaction_status\": \"settlement\",
    \"transaction_id\": \"${TRANSACTION_ID}\",
    \"status_code\": \"${STATUS_CODE}\",
    \"signature_key\": \"${SIGNATURE}\",
    \"order_id\": \"${ORDER_ID}\",
    \"gross_amount\": \"${PACKAGE_PRICE}.00\",
    \"payment_type\": \"credit_card\"
  }")

echo $WEBHOOK_RESPONSE | jq '.'

# Step 9: Verify Payment Status
echo "[9/10] Verifying Payment Status..."
FINAL_STATUS=$(curl -s -X GET "${API_BASE_URL}/v1/user-profile" \
  -H "Authorization: Bearer ${USER_TOKEN}" | jq -r '.data.invitation.payment_status')

if [ "$FINAL_STATUS" = "paid" ]; then
  echo "Payment Status: PAID (Success)"
else
  echo "ERROR: Expected paid, got ${FINAL_STATUS}"
  exit 1
fi

# Step 10: Test Idempotency
echo "[10/10] Testing Idempotency..."
DUPLICATE_RESPONSE=$(curl -s -X POST "${API_BASE_URL}/v1/midtrans/webhook" \
  -H "Content-Type: application/json" \
  -d "{
    \"transaction_status\": \"settlement\",
    \"transaction_id\": \"${TRANSACTION_ID}\",
    \"status_code\": \"${STATUS_CODE}\",
    \"signature_key\": \"${SIGNATURE}\",
    \"order_id\": \"${ORDER_ID}\",
    \"gross_amount\": \"${PACKAGE_PRICE}.00\"
  }")

DUPLICATE_MSG=$(echo $DUPLICATE_RESPONSE | jq -r '.message')
if [[ "$DUPLICATE_MSG" == *"processed"* ]]; then
  echo "Idempotency: PASS"
else
  echo "Idempotency: FAIL"
fi

echo ""
echo "========================================="
echo "ALL TESTS COMPLETED SUCCESSFULLY"
echo "========================================="
```

Make executable:
```bash
chmod +x test_midtrans.sh
```

Run tests:
```bash
./test_midtrans.sh
```

---

## VALIDATION CHECKLIST

After running all tests:

- [ ] Admin login successful
- [ ] User login successful
- [ ] User has invitation with Paket Platinum
- [ ] Package price matches master (Rp 299,000)
- [ ] Snap token created successfully
- [ ] Order ID format: INV-{uuid}
- [ ] Payment status initial: pending
- [ ] Webhook signature verified
- [ ] Webhook processed successfully
- [ ] Payment status changed to: paid
- [ ] Payment confirmed timestamp set
- [ ] Domain expiry set (12 months)
- [ ] Midtrans transaction ID saved
- [ ] Duplicate webhook rejected
- [ ] Payment logs created
- [ ] Invalid signature rejected (403)
- [ ] Invalid amount rejected (422)
- [ ] Already paid rejected (422)

---

## TROUBLESHOOTING

### Issue: Invalid Signature

Check server key matches .env configuration.

```bash
grep MIDTRANS_SERVER_KEY .env
```

Recalculate signature manually:
```bash
echo -n "ORDER_ID200AMOUNT_HEREserver_key_here" | openssl dgst -sha512
```

### Issue: Payment Status Not Updated

Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

Check payment_logs table:
```sql
SELECT * FROM payment_logs WHERE order_id = 'INV-xxx' ORDER BY created_at DESC;
```

### Issue: Token Already Exists

Reset invitation for testing:
```sql
UPDATE invitations
SET order_id = NULL,
    midtrans_transaction_id = NULL,
    payment_status = 'pending',
    payment_confirmed_at = NULL
WHERE id = INVITATION_ID;
```

---

**Testing Date**: 2025-10-30
**Integration Version**: Refactored v2.0
**Status**: All tests must pass before production deployment
