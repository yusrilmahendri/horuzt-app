#!/bin/bash

# Midtrans Webhook Testing Script
# This script simulates a Midtrans webhook call to update payment status

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Midtrans Webhook Testing Script ===${NC}\n"

# Check if order_id is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Order ID is required${NC}"
    echo "Usage: ./test_webhook.sh <order_id> [status]"
    echo ""
    echo "Example:"
    echo "  ./test_webhook.sh INV-1fc64dd6-2cbf-4b73-a9f6-a37ad28c5c88 settlement"
    echo ""
    echo "Available statuses:"
    echo "  - settlement (payment sukses - default)"
    echo "  - pending (menunggu pembayaran)"
    echo "  - deny (ditolak)"
    echo "  - cancel (dibatalkan)"
    echo "  - expire (kadaluarsa)"
    exit 1
fi

ORDER_ID=$1
TRANSACTION_STATUS=${2:-settlement}  # Default to settlement
TRANSACTION_ID="TEST-$(date +%s)"
STATUS_CODE="200"
GROSS_AMOUNT=""
SERVER_KEY=""

# Get order details from database
echo -e "${YELLOW}Fetching order details...${NC}"
ORDER_DETAILS=$(php artisan tinker --execute="
    \$invitation = App\Models\Invitation::where('order_id', '$ORDER_ID')->first();
    if (\$invitation) {
        echo json_encode([
            'user_id' => \$invitation->user_id,
            'invitation_id' => \$invitation->id,
            'payment_status' => \$invitation->payment_status,
            'amount' => \$invitation->package_price_snapshot ?? 0
        ]);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
" 2>/dev/null | grep -o '{.*}')

if echo "$ORDER_DETAILS" | grep -q '"error"'; then
    echo -e "${RED}Error: Order ID not found in database${NC}"
    exit 1
fi

GROSS_AMOUNT=$(echo "$ORDER_DETAILS" | grep -o '"amount":[0-9]*' | cut -d':' -f2)
USER_ID=$(echo "$ORDER_DETAILS" | grep -o '"user_id":[0-9]*' | cut -d':' -f2)
CURRENT_STATUS=$(echo "$ORDER_DETAILS" | grep -o '"payment_status":"[^"]*"' | cut -d'"' -f4)

echo -e "${GREEN}Order found!${NC}"
echo "  Order ID: $ORDER_ID"
echo "  Amount: Rp $GROSS_AMOUNT"
echo "  Current Status: $CURRENT_STATUS"
echo "  User ID: $USER_ID"
echo ""

# Get server key from config
SERVER_KEY=$(php artisan tinker --execute="
    \$service = new App\Services\MidtransService($USER_ID);
    \$reflection = new ReflectionClass(\$service);
    \$property = \$reflection->getProperty('serverKey');
    \$property->setAccessible(true);
    echo \$property->getValue(\$service);
" 2>/dev/null | tail -n 1)

# Generate signature
SIGNATURE_STRING="${ORDER_ID}${STATUS_CODE}${GROSS_AMOUNT}${SERVER_KEY}"
SIGNATURE_KEY=$(echo -n "$SIGNATURE_STRING" | openssl dgst -sha512 | awk '{print $2}')

echo -e "${YELLOW}Simulating webhook with status: ${TRANSACTION_STATUS}${NC}\n"

# Create webhook payload
WEBHOOK_PAYLOAD=$(cat <<EOF
{
  "transaction_time": "$(date -u +"%Y-%m-%d %H:%M:%S")",
  "transaction_status": "$TRANSACTION_STATUS",
  "transaction_id": "$TRANSACTION_ID",
  "status_message": "midtrans payment notification",
  "status_code": "$STATUS_CODE",
  "signature_key": "$SIGNATURE_KEY",
  "payment_type": "credit_card",
  "order_id": "$ORDER_ID",
  "merchant_id": "TEST-MERCHANT",
  "gross_amount": "$GROSS_AMOUNT.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
EOF
)

echo "Webhook Payload:"
echo "$WEBHOOK_PAYLOAD" | jq '.' 2>/dev/null || echo "$WEBHOOK_PAYLOAD"
echo ""

# Send webhook request
echo -e "${YELLOW}Sending webhook request...${NC}"
RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/midtrans/webhook \
  -H "Content-Type: application/json" \
  -d "$WEBHOOK_PAYLOAD")

echo ""
echo "Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

# Check updated status
echo -e "${YELLOW}Checking updated payment status...${NC}"
UPDATED_STATUS=$(php artisan tinker --execute="
    \$invitation = App\Models\Invitation::where('order_id', '$ORDER_ID')->first();
    if (\$invitation) {
        echo 'Payment Status: ' . \$invitation->payment_status . PHP_EOL;
        if (\$invitation->payment_confirmed_at) {
            echo 'Confirmed At: ' . \$invitation->payment_confirmed_at . PHP_EOL;
        }
        if (\$invitation->domain_expires_at) {
            echo 'Domain Expires: ' . \$invitation->domain_expires_at . PHP_EOL;
        }
    }
" 2>/dev/null | grep -E "(Payment Status|Confirmed At|Domain Expires)")

echo "$UPDATED_STATUS"

if echo "$UPDATED_STATUS" | grep -q "paid"; then
    echo -e "\n${GREEN}✓ Webhook processed successfully! Payment status updated.${NC}"
else
    echo -e "\n${RED}✗ Payment status not updated. Check logs for errors.${NC}"
fi

# Show recent payment logs
echo -e "\n${YELLOW}Recent Payment Logs:${NC}"
php artisan tinker --execute="
    \$logs = App\Models\PaymentLog::where('order_id', '$ORDER_ID')
        ->orderBy('created_at', 'desc')
        ->limit(3)
        ->get(['event_type', 'transaction_status', 'created_at', 'notes']);
    foreach (\$logs as \$log) {
        echo sprintf('[%s] %s - %s %s',
            \$log->created_at->format('Y-m-d H:i:s'),
            \$log->event_type,
            \$log->transaction_status,
            \$log->notes ? '(' . \$log->notes . ')' : ''
        ) . PHP_EOL;
    }
" 2>/dev/null | grep -E "^\[20"

echo ""
