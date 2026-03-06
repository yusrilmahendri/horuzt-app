#!/bin/bash

echo "================================================"
echo "Laravel Clean Reset - Keep Admin Only"
echo "================================================"
echo ""

cd "$(dirname "$0")"

echo "Step 1: Stopping Laravel server (if running)..."
pkill -f "php artisan serve" 2>/dev/null && echo "  ✓ Server stopped" || echo "  ℹ No server running"
echo ""

echo "Step 2: Cleaning all non-admin user data..."
php clean_users.php
if [ $? -ne 0 ]; then
    echo "  ✗ Clean failed. Aborting."
    exit 1
fi
echo ""

echo "Step 3: Clearing Laravel caches..."
php artisan config:clear > /dev/null 2>&1
php artisan cache:clear > /dev/null 2>&1
php artisan route:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1
echo "  ✓ All caches cleared"
echo ""

echo "Step 4: Running database seeders (if any)..."
php artisan db:seed --class=PaketUndanganSeeder 2>/dev/null && echo "  ✓ PaketUndanganSeeder completed" || echo "  ℹ PaketUndanganSeeder skipped"
php artisan db:seed --class=MethodePembayaranSeeder 2>/dev/null && echo "  ✓ MethodePembayaranSeeder completed" || echo "  ℹ MethodePembayaranSeeder skipped"
echo ""

echo "Step 5: Starting Laravel server..."
php artisan serve > /dev/null 2>&1 &
SERVER_PID=$!
sleep 2

if ps -p $SERVER_PID > /dev/null; then
    echo "  ✓ Server started (PID: $SERVER_PID)"
    echo "  ✓ Accessible at http://127.0.0.1:8000"
else
    echo "  ✗ Failed to start server"
    exit 1
fi

echo ""
echo "================================================"
echo "✓ Clean reset completed successfully"
echo "================================================"
echo ""
echo "Current state:"
echo "  • Admin user preserved"
echo "  • All non-admin users deleted"
echo "  • All related data cleaned"
echo "  • Server running on http://127.0.0.1:8000"
echo ""
echo "You can now create new users from scratch."
echo ""
