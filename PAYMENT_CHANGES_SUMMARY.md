# Payment System Changes Summary

**Date:** 2025-11-04
**Environment:** Production (sena-digital.com)
**Impact:** Frontend Angular Application

---

## Problem Statement

Payment was successful in Midtrans but database status was not updating to "paid".

### Root Cause

Webhook URL in Midtrans Dashboard was configured to production domain (`https://www.sena-digital.com/api/v1/midtrans/webhook`), but testing was done on localhost. When payment succeeded in sandbox:

1. Midtrans sent webhook to production server
2. Production database was updated
3. Local development database remained unchanged

---

## Solution Implemented

Created a new Payment Status Check endpoint that allows frontend to verify payment status directly from Midtrans API and trigger database update.

### Benefits

1. **Works in Local Development:** No need for webhook to reach localhost
2. **Immediate Feedback:** Frontend gets instant confirmation without waiting for webhook
3. **Redundancy:** Acts as fallback if webhook fails or is delayed
4. **Production Ready:** Works seamlessly in both development and production

---

## Changes Made

### 1. Backend Changes

#### New Endpoint
**File:** `app/Http/Controllers/MidtransController.php`

Added method: `checkPaymentStatus(Request $request): JsonResponse`

**Purpose:** Query Midtrans API for transaction status and update database

**Location:** Line 140-263

#### Service Enhancement
**File:** `app/Services/MidtransService.php`

Added method: `configureMidtrans(): void`

**Purpose:** Expose Midtrans configuration setup for external calls

**Location:** Line 66-69

#### Route Addition
**File:** `routes/api.php`

Added route: `POST /v1/midtrans/check-status`

**Location:** Line 64

#### Database Migration
**File:** `database/migrations/2025_11_04_044335_add_status_check_to_payment_logs_event_type.php`

**Purpose:** Add 'status_check' to payment_logs event_type ENUM

**Change:** Extended ENUM values from:
```sql
['token_request', 'token_response', 'webhook_received', 'webhook_processed', 'error']
```
to:
```sql
['token_request', 'token_response', 'webhook_received', 'webhook_processed', 'status_check', 'error']
```

### 2. Frontend Integration Required

#### New API Call After Payment Success

**When:** After Snap payment succeeds (onSuccess callback)

**Endpoint:** `POST /api/v1/midtrans/check-status`

**Request:**
```json
{
  "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7"
}
```

**Response (Success):**
```json
{
  "success": true,
  "payment_status": "paid",
  "message": "Payment confirmed successfully",
  "data": {
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "transaction_id": "411f77e7-e47d-49d1-95a5-85fe74ef250b",
    "payment_confirmed_at": "2025-11-04T04:44:10.000000Z",
    "domain_expires_at": "2033-05-04T04:44:10.000000Z"
  }
}
```

---

## Frontend Implementation Steps

### Step 1: Update Payment Service

Add new method to your payment service:

```typescript
checkPaymentStatus(orderId: string): Observable<PaymentStatusResponse> {
  return this.http.post<PaymentStatusResponse>(
    `${this.apiUrl}/v1/midtrans/check-status`,
    { order_id: orderId }
  );
}
```

### Step 2: Modify Snap onSuccess Callback

**BEFORE:**
```typescript
snap.pay(snapToken, {
  onSuccess: (result) => {
    // Redirect immediately
    this.router.navigate(['/payment/success']);
  }
});
```

**AFTER:**
```typescript
snap.pay(snapToken, {
  onSuccess: (result) => {
    // Verify payment status before redirect
    this.verifyPaymentStatus(result.order_id);
  }
});

private verifyPaymentStatus(orderId: string): void {
  this.paymentService.checkPaymentStatus(orderId).subscribe({
    next: (response) => {
      if (response.success && response.payment_status === 'paid') {
        this.router.navigate(['/payment/success'], {
          queryParams: {
            order_id: orderId,
            transaction_id: response.data.transaction_id,
            confirmed_at: response.data.payment_confirmed_at
          }
        });
      } else {
        this.router.navigate(['/payment/pending'], {
          queryParams: { order_id: orderId }
        });
      }
    },
    error: (error) => {
      console.error('Failed to verify payment:', error);
      // Still redirect to success, webhook will handle it
      this.router.navigate(['/payment/success'], {
        queryParams: { order_id: orderId }
      });
    }
  });
}
```

### Step 3: Add Polling for Pending Payments

If payment status is "pending", poll the status check endpoint every 3 seconds:

```typescript
private startPolling(orderId: string): void {
  interval(3000)
    .pipe(
      takeWhile(() => this.pollingAttempts < 10),
      switchMap(() => this.paymentService.checkPaymentStatus(orderId))
    )
    .subscribe({
      next: (response) => {
        if (response.payment_status === 'paid') {
          // Update UI to show success
          this.paymentStatus = 'paid';
          // Stop polling
        }
      }
    });
}
```

---

## Files to Create in Angular Project

### 1. Payment Service Interface

**File:** `src/app/interfaces/payment.interface.ts`

```typescript
export interface SnapTokenRequest {
  invitation_id: number;
  amount: number;
  customer_details?: {
    first_name: string;
    last_name?: string;
    email: string;
    phone: string;
  };
  item_details?: Array<{
    id: string;
    name: string;
    price: number;
    quantity: number;
  }>;
}

export interface SnapTokenResponse {
  success: boolean;
  data: {
    snap_token: string;
    order_id: string;
    gross_amount: number;
    invitation_id: number;
    expires_at: string;
  };
  message: string;
}

export interface PaymentStatusResponse {
  success: boolean;
  payment_status: 'paid' | 'pending' | 'failed' | 'refunded';
  transaction_status?: string;
  message: string;
  data: {
    order_id: string;
    transaction_id?: string;
    payment_confirmed_at?: string;
    domain_expires_at?: string;
  };
}
```

### 2. Payment Service

**File:** `src/app/services/payment.service.ts`

```typescript
import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { SnapTokenRequest, SnapTokenResponse, PaymentStatusResponse } from '../interfaces/payment.interface';

@Injectable({
  providedIn: 'root'
})
export class PaymentService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  createSnapToken(userId: number, request: SnapTokenRequest): Observable<SnapTokenResponse> {
    const params = new HttpParams().set('user_id', userId.toString());
    return this.http.post<SnapTokenResponse>(
      `${this.apiUrl}/midtrans/create-snap-token`,
      request,
      { params }
    );
  }

  checkPaymentStatus(orderId: string): Observable<PaymentStatusResponse> {
    return this.http.post<PaymentStatusResponse>(
      `${this.apiUrl}/v1/midtrans/check-status`,
      { order_id: orderId }
    );
  }
}
```

### 3. Environment Configuration

**File:** `src/environments/environment.prod.ts`

```typescript
export const environment = {
  production: true,
  apiUrl: 'https://www.sena-digital.com/api',
  midtransClientKey: 'SB-Mid-client-NjshfjtIODw5zt75', // Replace with production key
  midtransScriptUrl: 'https://app.midtrans.com/snap/snap.js' // Production URL
};
```

---

## Testing Verification

### Test Successfully Completed

**Order ID:** `INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7`

**Test Result:**
```json
{
  "success": true,
  "payment_status": "paid",
  "message": "Payment confirmed successfully",
  "data": {
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "transaction_id": "411f77e7-e47d-49d1-95a5-85fe74ef250b",
    "payment_confirmed_at": "2025-11-04T04:44:10.000000Z",
    "domain_expires_at": "2033-05-04T04:44:10.000000Z"
  }
}
```

**Database Verification:**
```sql
SELECT order_id, payment_status, payment_confirmed_at
FROM invitations
WHERE order_id = 'INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7';

-- Result:
-- payment_status: "paid"
-- payment_confirmed_at: "2025-11-04 04:44:10"
```

---

## Production Deployment Steps

### Backend

1. **Pull latest code:**
   ```bash
   git pull origin main
   ```

2. **Run migrations:**
   ```bash
   php artisan migrate --force
   ```

3. **Clear caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```

4. **Verify webhook URL in Midtrans Dashboard:**
   - Login to https://dashboard.midtrans.com
   - Go to Settings > Configuration
   - Ensure Payment Notification URL is: `https://www.sena-digital.com/api/v1/midtrans/webhook`

### Frontend

1. **Update payment component:**
   - Add status check call in `onSuccess` callback
   - Implement polling for pending payments

2. **Update environment files:**
   - Production API URL
   - Production Midtrans Client Key
   - Production Snap.js URL

3. **Build and deploy:**
   ```bash
   ng build --configuration production
   ```

4. **Test in production:**
   - Create test invitation
   - Complete payment with sandbox card
   - Verify database updates
   - Verify success page displays

---

## Monitoring

### Check Payment Logs

```sql
-- Recent status checks
SELECT
  order_id,
  event_type,
  transaction_status,
  created_at
FROM payment_logs
WHERE event_type = 'status_check'
ORDER BY created_at DESC
LIMIT 20;

-- Payment status distribution
SELECT
  payment_status,
  COUNT(*) as count
FROM invitations
WHERE payment_status IS NOT NULL
GROUP BY payment_status;
```

### Laravel Logs

```bash
# Monitor payment operations
tail -f storage/logs/laravel.log | grep -i "payment\|midtrans"
```

---

## Backward Compatibility

### Existing Flow Still Works

The webhook endpoint remains unchanged and continues to work. The status check endpoint is an additional mechanism, not a replacement.

**Flow:**
1. Webhook updates database (primary mechanism)
2. Status check updates database (fallback/immediate verification)
3. Duplicate check prevents double-processing

---

## Support Contacts

**Backend Team:**
- Review: `app/Http/Controllers/MidtransController.php`
- Logs: `storage/logs/laravel.log`
- Database: `payment_logs` and `invitations` tables

**Frontend Team:**
- Integration: See `PAYMENT_API_CONTRACT.md`
- Examples: Complete Angular code provided above

---

## Quick Reference

### New Endpoint
```
POST /api/v1/midtrans/check-status
```

### Request
```json
{
  "order_id": "INV-xxx"
}
```

### Response
```json
{
  "success": true,
  "payment_status": "paid",
  "data": {
    "order_id": "INV-xxx",
    "transaction_id": "xxx",
    "payment_confirmed_at": "2025-11-04T04:44:10.000000Z",
    "domain_expires_at": "2033-05-04T04:44:10.000000Z"
  }
}
```

### Payment Status Values
- `paid`: Payment successful
- `pending`: Payment processing
- `failed`: Payment denied/cancelled/expired
- `refunded`: Payment refunded

---

## Related Documentation

1. `PAYMENT_API_CONTRACT.md` - Complete API documentation with examples
2. `MIDTRANS_API_FLOW.md` - Detailed Midtrans integration flow
3. Laravel logs: `storage/logs/laravel.log`
4. Database schema: `database/migrations/`

---

## Questions?

Review the comprehensive documentation in `PAYMENT_API_CONTRACT.md` or check Laravel logs for debugging.
