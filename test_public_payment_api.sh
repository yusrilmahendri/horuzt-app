#!/bin/bash

# Test script for Public Payment API with query parameter

API_URL="http://127.0.0.1:8000/api"
USER_ID=5
INVITATION_ID=4
AMOUNT=199000

echo "========================================"
echo "PUBLIC PAYMENT API TEST"
echo "========================================"
echo ""
echo "API URL: ${API_URL}/midtrans/create-snap-token?user_id=${USER_ID}"
echo ""

# Prepare invitation (clear order_id if exists)
echo "[1] Preparing invitation for testing..."
php artisan tinker --execute="
\$inv = \App\Models\Invitation::find(${INVITATION_ID});
if (\$inv && \$inv->order_id) {
    \$inv->update(['order_id' => null]);
    echo 'Cleared order_id for invitation ${INVITATION_ID}' . PHP_EOL;
} else {
    echo 'Invitation ${INVITATION_ID} ready for testing' . PHP_EOL;
}
" 2>/dev/null

echo ""

# Test API call
echo "[2] Creating Snap Token..."
echo ""

RESPONSE=$(cat <<'EOF' | curl -s -X POST "${API_URL}/midtrans/create-snap-token?user_id=${USER_ID}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @-
{
  "invitation_id": ${INVITATION_ID},
  "amount": ${AMOUNT},
  "customer_details": {
    "first_name": "kosa",
    "last_name": "",
    "email": "kosa@gmail.com",
    "phone": "0847824294224"
  },
  "item_details": [
    {
      "id": "paket-2",
      "name": "Paket Gold",
      "price": ${AMOUNT},
      "quantity": 1
    }
  ]
}
EOF
)

echo "Response:"
echo "$RESPONSE"
echo ""

# Parse response
SUCCESS=$(echo "$RESPONSE" | grep -o '"success":true' | head -1)

if [ ! -z "$SUCCESS" ]; then
    echo "========================================"
    echo "✅ TEST PASSED!"
    echo "========================================"
    echo ""
    SNAP_TOKEN=$(echo "$RESPONSE" | grep -o '"snap_token":"[^"]*"' | cut -d'"' -f4)
    ORDER_ID=$(echo "$RESPONSE" | grep -o '"order_id":"[^"]*"' | cut -d'"' -f4)
    echo "Snap Token: ${SNAP_TOKEN:0:40}..."
    echo "Order ID: $ORDER_ID"
    echo ""
    echo "🎯 API is working correctly!"
    echo "Frontend can now use: ?user_id=${USER_ID}"
    echo ""
else
    echo "========================================"
    echo "❌ TEST FAILED!"
    echo "========================================"
    echo ""
    echo "Check the error response above."
    echo ""
fi
