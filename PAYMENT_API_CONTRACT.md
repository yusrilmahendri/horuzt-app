# Payment API Contract - Frontend Integration Guide

**Last Updated:** 2025-11-04
**API Version:** v1
**Base URL Production:** `https://www.sena-digital.com/api`
**Base URL Local:** `http://localhost:8000/api`

## Overview

This document describes the complete payment integration flow using Midtrans Snap.js for the wedding invitation SaaS application. It includes the new payment status verification endpoint that solves webhook delivery issues in local development.

---

## Payment Flow Architecture

### Standard Flow (Webhook-Based)

```
User → Frontend → Create Snap Token → Midtrans Snap UI → Payment Success
                                                              ↓
                                                          Webhook
                                                              ↓
                                                    Backend Updates DB
                                                              ↓
                                                   Frontend Polls Status
```

### Enhanced Flow (Status Check Fallback)

```
User → Frontend → Create Snap Token → Midtrans Snap UI → Payment Success
                                                              ↓
                                                   Frontend Calls Status Check
                                                              ↓
                                                 Backend Queries Midtrans API
                                                              ↓
                                                    Backend Updates DB
                                                              ↓
                                                   Return Updated Status
```

---

## API Endpoints

### 1. Create Snap Token

**Endpoint:** `POST /midtrans/create-snap-token`
**Authentication:** None (uses query parameter `user_id`)
**Purpose:** Generate Midtrans Snap token for payment

#### Request

**Query Parameters:**
- `user_id` (required): User ID for the transaction

**Headers:**
```http
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "invitation_id": 17,
  "amount": 299000,
  "customer_details": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+6281234567890"
  },
  "item_details": [
    {
      "id": "paket-1",
      "name": "Premium Wedding Package",
      "price": 299000,
      "quantity": 1
    }
  ]
}
```

**Field Descriptions:**
- `invitation_id` (integer, required): ID of the invitation being paid for
- `amount` (number, required): Total payment amount in IDR
- `customer_details` (object, optional): Customer information
  - `first_name` (string): Customer first name
  - `last_name` (string): Customer last name
  - `email` (string): Customer email
  - `phone` (string): Customer phone with country code
- `item_details` (array, optional): Array of items being purchased

#### Success Response

**HTTP Status:** `201 Created`

```json
{
  "success": true,
  "data": {
    "snap_token": "66e4fa55-fdac-4ef9-91b5-733b5d9f138e",
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "gross_amount": 299000,
    "invitation_id": 17,
    "expires_at": "2025-11-05T04:26:53.000000Z"
  },
  "message": "Snap token created successfully"
}
```

**Response Fields:**
- `snap_token` (string): Token to be used with Snap.js
- `order_id` (string): Unique order identifier (UUID format with INV- prefix)
- `gross_amount` (number): Total amount in IDR
- `invitation_id` (integer): Associated invitation ID
- `expires_at` (string, ISO 8601): Token expiration timestamp (24 hours from creation)

#### Error Responses

**Validation Error - 422 Unprocessable Entity**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invitation_id": ["The invitation id field is required."],
    "amount": ["The amount must be at least 10000."]
  }
}
```

**Midtrans Service Error - 503 Service Unavailable**
```json
{
  "success": false,
  "message": "Failed to generate payment token. Please try again later."
}
```

**Server Error - 500 Internal Server Error**
```json
{
  "success": false,
  "message": "An unexpected error occurred. Please try again later."
}
```

---

### 2. Check Payment Status (NEW)

**Endpoint:** `POST /v1/midtrans/check-status`
**Authentication:** None
**Purpose:** Verify payment status from Midtrans and update database

#### Request

**Headers:**
```http
Content-Type: application/json
Accept: application/json
```

**Body:**
```json
{
  "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7"
}
```

**Field Descriptions:**
- `order_id` (string, required): Order ID returned from Create Snap Token endpoint

#### Success Responses

**Payment Confirmed - 200 OK**
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

**Payment Already Confirmed - 200 OK**
```json
{
  "success": true,
  "payment_status": "paid",
  "message": "Payment already confirmed",
  "data": {
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "payment_confirmed_at": "2025-11-04T04:44:10.000000Z",
    "domain_expires_at": "2033-05-04T04:44:10.000000Z"
  }
}
```

**Payment Pending - 200 OK**
```json
{
  "success": true,
  "payment_status": "pending",
  "transaction_status": "pending",
  "message": "Payment status retrieved",
  "data": {
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "transaction_id": null
  }
}
```

**Payment Failed - 200 OK**
```json
{
  "success": true,
  "payment_status": "failed",
  "transaction_status": "deny",
  "message": "Payment status retrieved",
  "data": {
    "order_id": "INV-94f2e5cd-bc8f-4cc2-9491-1851fe6763b7",
    "transaction_id": "411f77e7-e47d-49d1-95a5-85fe74ef250b"
  }
}
```

**Response Fields:**
- `payment_status` (string): Overall payment status
  - `paid`: Payment successful and confirmed
  - `pending`: Payment pending or processing
  - `failed`: Payment denied, cancelled, or expired
  - `refunded`: Payment refunded
- `transaction_status` (string): Midtrans transaction status
  - `capture`: Card payment captured
  - `settlement`: Payment settled
  - `pending`: Payment pending
  - `deny`: Payment denied
  - `cancel`: Payment cancelled
  - `expire`: Payment expired
  - `refund`: Payment refunded
- `message` (string): Human-readable status message
- `data` (object): Transaction details
  - `order_id` (string): Order identifier
  - `transaction_id` (string|null): Midtrans transaction ID
  - `payment_confirmed_at` (string|null, ISO 8601): Payment confirmation timestamp
  - `domain_expires_at` (string|null, ISO 8601): Domain expiration date based on package duration

#### Error Responses

**Missing Order ID - 400 Bad Request**
```json
{
  "success": false,
  "message": "Order ID is required"
}
```

**Order Not Found - 404 Not Found**
```json
{
  "success": false,
  "message": "Order not found"
}
```

**Midtrans API Error - 503 Service Unavailable**
```json
{
  "success": false,
  "message": "Failed to check payment status from Midtrans",
  "error": "Midtrans API error message"
}
```

**Server Error - 500 Internal Server Error**
```json
{
  "success": false,
  "message": "Failed to check payment status"
}
```

---

### 3. Webhook Handler (Backend Only)

**Endpoint:** `POST /v1/midtrans/webhook`
**Authentication:** Signature verification
**Purpose:** Receive payment notifications from Midtrans servers

**Note:** This endpoint is called by Midtrans servers, not by your frontend.

**Configured Webhook URL:**
Production: `https://www.sena-digital.com/api/v1/midtrans/webhook`

---

## Payment Status Mapping

### Midtrans Transaction Status → Application Payment Status

| Midtrans Status | Application Status | Description |
|----------------|-------------------|-------------|
| `capture` | `paid` | Card payment captured successfully |
| `settlement` | `paid` | Payment settled to merchant |
| `pending` | `pending` | Payment awaiting completion |
| `deny` | `failed` | Payment denied by bank/fraud detection |
| `cancel` | `failed` | Payment cancelled by user or system |
| `expire` | `failed` | Payment session expired |
| `refund` | `refunded` | Payment refunded to customer |

---

## Angular Frontend Integration

### 1. Service Setup

Create a payment service:

```typescript
// src/app/services/payment.service.ts
import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

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

### 2. Environment Configuration

```typescript
// src/environments/environment.prod.ts
export const environment = {
  production: true,
  apiUrl: 'https://www.sena-digital.com/api',
  midtransClientKey: 'SB-Mid-client-NjshfjtIODw5zt75', // Your Midtrans Client Key
  midtransScriptUrl: 'https://app.sandbox.midtrans.com/snap/snap.js' // Use production URL in production
};

// src/environments/environment.ts (for development)
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api',
  midtransClientKey: 'SB-Mid-client-NjshfjtIODw5zt75',
  midtransScriptUrl: 'https://app.sandbox.midtrans.com/snap/snap.js'
};
```

### 3. Load Midtrans Snap.js

Add to `index.html`:

```html
<!-- src/index.html -->
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sena Digital</title>
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <!-- Midtrans Snap.js -->
  <script
    type="text/javascript"
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="SB-Mid-client-NjshfjtIODw5zt75">
  </script>
</head>
<body>
  <app-root></app-root>
</body>
</html>
```

Or load dynamically in component:

```typescript
// src/app/components/payment/payment.component.ts
import { Component, OnInit } from '@angular/core';
import { environment } from '../../../environments/environment';

@Component({
  selector: 'app-payment',
  templateUrl: './payment.component.html',
  styleUrls: ['./payment.component.scss']
})
export class PaymentComponent implements OnInit {
  private snapLoaded = false;

  ngOnInit(): void {
    this.loadSnapScript();
  }

  private loadSnapScript(): Promise<void> {
    return new Promise((resolve, reject) => {
      if (this.snapLoaded) {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = environment.midtransScriptUrl;
      script.setAttribute('data-client-key', environment.midtransClientKey);
      script.onload = () => {
        this.snapLoaded = true;
        resolve();
      };
      script.onerror = () => reject(new Error('Failed to load Snap.js'));
      document.head.appendChild(script);
    });
  }
}
```

### 4. Payment Component Implementation

```typescript
// src/app/components/payment/payment.component.ts
import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { PaymentService, SnapTokenRequest } from '../../services/payment.service';
import { finalize } from 'rxjs/operators';

declare const snap: any;

@Component({
  selector: 'app-payment',
  templateUrl: './payment.component.html',
  styleUrls: ['./payment.component.scss']
})
export class PaymentComponent implements OnInit {
  loading = false;
  errorMessage = '';
  invitationId: number;
  userId: number;
  amount: number;
  packageName: string;

  constructor(
    private paymentService: PaymentService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    // Get data from route params or state
    this.invitationId = this.route.snapshot.queryParams['invitation_id'];
    this.userId = this.route.snapshot.queryParams['user_id'];
    this.amount = this.route.snapshot.queryParams['amount'];
    this.packageName = this.route.snapshot.queryParams['package_name'];
  }

  processPayment(): void {
    this.loading = true;
    this.errorMessage = '';

    const request: SnapTokenRequest = {
      invitation_id: this.invitationId,
      amount: this.amount,
      customer_details: {
        first_name: 'Customer', // Get from user profile
        email: 'customer@example.com', // Get from user profile
        phone: '+6281234567890' // Get from user profile
      },
      item_details: [
        {
          id: `paket-${this.invitationId}`,
          name: this.packageName,
          price: this.amount,
          quantity: 1
        }
      ]
    };

    this.paymentService.createSnapToken(this.userId, request)
      .pipe(finalize(() => this.loading = false))
      .subscribe({
        next: (response) => {
          if (response.success) {
            this.openSnapPayment(response.data.snap_token, response.data.order_id);
          } else {
            this.errorMessage = response.message;
          }
        },
        error: (error) => {
          console.error('Failed to create snap token:', error);
          this.errorMessage = error.error?.message || 'Failed to initialize payment. Please try again.';
        }
      });
  }

  private openSnapPayment(snapToken: string, orderId: string): void {
    snap.pay(snapToken, {
      onSuccess: (result: any) => {
        console.log('Payment success:', result);
        this.verifyPaymentStatus(orderId);
      },
      onPending: (result: any) => {
        console.log('Payment pending:', result);
        this.router.navigate(['/payment/pending'], {
          queryParams: { order_id: orderId }
        });
      },
      onError: (result: any) => {
        console.error('Payment error:', result);
        this.router.navigate(['/payment/error'], {
          queryParams: { order_id: orderId }
        });
      },
      onClose: () => {
        console.log('Payment popup closed without completing payment');
      }
    });
  }

  private verifyPaymentStatus(orderId: string): void {
    this.loading = true;

    this.paymentService.checkPaymentStatus(orderId)
      .pipe(finalize(() => this.loading = false))
      .subscribe({
        next: (response) => {
          if (response.success && response.payment_status === 'paid') {
            // Payment confirmed, redirect to success page
            this.router.navigate(['/payment/success'], {
              queryParams: {
                order_id: orderId,
                transaction_id: response.data.transaction_id,
                confirmed_at: response.data.payment_confirmed_at
              }
            });
          } else if (response.payment_status === 'pending') {
            // Still pending, redirect to pending page
            this.router.navigate(['/payment/pending'], {
              queryParams: { order_id: orderId }
            });
          } else {
            // Payment failed
            this.router.navigate(['/payment/error'], {
              queryParams: { order_id: orderId }
            });
          }
        },
        error: (error) => {
          console.error('Failed to verify payment status:', error);
          // Even if verification fails, redirect to success page
          // Webhook will handle the update
          this.router.navigate(['/payment/success'], {
            queryParams: { order_id: orderId }
          });
        }
      });
  }
}
```

### 5. Payment Status Page (Success/Pending/Error)

```typescript
// src/app/components/payment-status/payment-status.component.ts
import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { PaymentService } from '../../services/payment.service';
import { interval, Subscription } from 'rxjs';
import { switchMap, takeWhile } from 'rxjs/operators';

@Component({
  selector: 'app-payment-status',
  templateUrl: './payment-status.component.html',
  styleUrls: ['./payment-status.component.scss']
})
export class PaymentStatusComponent implements OnInit {
  orderId: string;
  statusType: 'success' | 'pending' | 'error';
  paymentStatus: string;
  transactionId: string;
  confirmedAt: string;
  loading = true;

  private pollingSubscription: Subscription;
  private maxPollingAttempts = 10;
  private pollingAttempts = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private paymentService: PaymentService
  ) {}

  ngOnInit(): void {
    this.orderId = this.route.snapshot.queryParams['order_id'];
    this.transactionId = this.route.snapshot.queryParams['transaction_id'];
    this.confirmedAt = this.route.snapshot.queryParams['confirmed_at'];

    // Determine status type from route
    const url = this.router.url;
    if (url.includes('/success')) {
      this.statusType = 'success';
    } else if (url.includes('/pending')) {
      this.statusType = 'pending';
      this.startPolling();
    } else {
      this.statusType = 'error';
    }

    this.checkStatus();
  }

  ngOnDestroy(): void {
    if (this.pollingSubscription) {
      this.pollingSubscription.unsubscribe();
    }
  }

  private checkStatus(): void {
    if (!this.orderId) {
      this.loading = false;
      return;
    }

    this.paymentService.checkPaymentStatus(this.orderId).subscribe({
      next: (response) => {
        this.loading = false;
        this.paymentStatus = response.payment_status;

        if (response.payment_status === 'paid') {
          this.statusType = 'success';
          this.transactionId = response.data.transaction_id;
          this.confirmedAt = response.data.payment_confirmed_at;
          this.stopPolling();
        }
      },
      error: (error) => {
        console.error('Failed to check payment status:', error);
        this.loading = false;
      }
    });
  }

  private startPolling(): void {
    // Poll every 3 seconds for pending payments
    this.pollingSubscription = interval(3000)
      .pipe(
        takeWhile(() => this.pollingAttempts < this.maxPollingAttempts),
        switchMap(() => {
          this.pollingAttempts++;
          return this.paymentService.checkPaymentStatus(this.orderId);
        })
      )
      .subscribe({
        next: (response) => {
          this.paymentStatus = response.payment_status;

          if (response.payment_status === 'paid') {
            this.statusType = 'success';
            this.transactionId = response.data.transaction_id;
            this.confirmedAt = response.data.payment_confirmed_at;
            this.stopPolling();

            // Update URL without navigation
            this.router.navigate([], {
              relativeTo: this.route,
              queryParams: {
                order_id: this.orderId,
                transaction_id: this.transactionId,
                confirmed_at: this.confirmedAt
              },
              queryParamsHandling: 'merge'
            });
          } else if (response.payment_status === 'failed') {
            this.statusType = 'error';
            this.stopPolling();
          }
        },
        error: (error) => {
          console.error('Polling error:', error);
        }
      });
  }

  private stopPolling(): void {
    if (this.pollingSubscription) {
      this.pollingSubscription.unsubscribe();
    }
  }
}
```

---

## Error Handling Best Practices

### 1. Network Errors

```typescript
this.paymentService.createSnapToken(userId, request)
  .pipe(
    retry(2), // Retry failed requests twice
    catchError((error) => {
      if (error.status === 0) {
        // Network error
        return throwError(() => new Error('Network error. Please check your connection.'));
      }
      return throwError(() => error);
    })
  )
  .subscribe({
    next: (response) => { /* handle success */ },
    error: (error) => {
      this.errorMessage = error.message || 'An error occurred';
    }
  });
```

### 2. Validation Errors

```typescript
.subscribe({
  error: (error) => {
    if (error.status === 422) {
      // Validation error
      const errors = error.error.errors;
      this.validationErrors = errors;

      // Display field-specific errors
      Object.keys(errors).forEach(field => {
        const messages = errors[field];
        console.error(`${field}: ${messages.join(', ')}`);
      });
    }
  }
});
```

### 3. Timeout Handling

```typescript
import { timeout, catchError } from 'rxjs/operators';

this.paymentService.checkPaymentStatus(orderId)
  .pipe(
    timeout(30000), // 30 second timeout
    catchError((error) => {
      if (error.name === 'TimeoutError') {
        return throwError(() => new Error('Request timed out. Please try again.'));
      }
      return throwError(() => error);
    })
  )
  .subscribe({ /* ... */ });
```

---

## Testing Guide

### 1. Sandbox Test Cards

Use these test cards in sandbox mode:

**Successful Payment:**
- Card Number: `4811 1111 1111 1114`
- Expiry: Any future date (e.g., `12/25`)
- CVV: `123`
- OTP/3DS: `112233`

**Failed Payment:**
- Card Number: `4911 1111 1111 1113`
- Expiry: Any future date
- CVV: `123`
- OTP/3DS: `112233`

### 2. Testing Workflow

1. Create invitation
2. Select package
3. Click "Pay Now"
4. Enter test card details
5. Complete 3DS verification
6. Frontend calls status check endpoint
7. Verify database updates
8. Check success page displays correct information

### 3. Local Development Testing

**Option 1: Use Status Check Endpoint (Recommended)**
- Webhook goes to production
- Frontend calls `/v1/midtrans/check-status` after payment
- Local database updates via status check

**Option 2: Use ngrok for Webhook**
```bash
# Start Laravel
php artisan serve

# Start ngrok in another terminal
ngrok http 8000

# Update Midtrans Dashboard webhook URL to ngrok URL
# Example: https://abc123.ngrok-free.app/api/v1/midtrans/webhook
```

---

## Production Deployment Checklist

### Backend

- [ ] Update `.env` with production Midtrans credentials
  ```env
  MIDTRANS_SERVER_KEY=your_production_server_key
  MIDTRANS_CLIENT_KEY=your_production_client_key
  MIDTRANS_IS_PRODUCTION=true
  ```

- [ ] Verify webhook URL in Midtrans Dashboard
  ```
  https://www.sena-digital.com/api/v1/midtrans/webhook
  ```

- [ ] Run database migrations
  ```bash
  php artisan migrate --force
  ```

- [ ] Clear and optimize caches
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

### Frontend

- [ ] Update `environment.prod.ts` with production API URL
  ```typescript
  apiUrl: 'https://www.sena-digital.com/api'
  ```

- [ ] Update Midtrans Snap.js URL to production
  ```typescript
  midtransScriptUrl: 'https://app.midtrans.com/snap/snap.js'
  ```

- [ ] Update Client Key to production key
  ```typescript
  midtransClientKey: 'Mid-client-YOUR_PRODUCTION_CLIENT_KEY'
  ```

- [ ] Build for production
  ```bash
  ng build --configuration production
  ```

- [ ] Test payment flow in production environment

### Security

- [ ] Ensure HTTPS is enabled
- [ ] Verify CORS settings allow your frontend domain
- [ ] Check rate limiting is configured
- [ ] Review error messages don't expose sensitive data
- [ ] Verify webhook signature validation is enabled

---

## Monitoring and Logging

### Check Payment Logs

```sql
-- Recent payment logs
SELECT * FROM payment_logs
ORDER BY created_at DESC
LIMIT 20;

-- Failed transactions
SELECT * FROM payment_logs
WHERE event_type = 'error'
ORDER BY created_at DESC;

-- Status check logs
SELECT * FROM payment_logs
WHERE event_type = 'status_check'
ORDER BY created_at DESC;

-- Payment summary by status
SELECT payment_status, COUNT(*) as count
FROM invitations
GROUP BY payment_status;
```

### Laravel Logs

```bash
# View recent logs
tail -f storage/logs/laravel.log

# Search for payment errors
grep -i "payment\|midtrans\|webhook" storage/logs/laravel.log | tail -50
```

---

## Troubleshooting

### Issue: Payment succeeds but database not updated

**Cause:** Webhook not reaching backend or status check not called

**Solution:**
1. Check if frontend calls `/v1/midtrans/check-status` after payment success
2. Verify webhook URL in Midtrans Dashboard is correct
3. Check Laravel logs for errors
4. Verify database connection is working

### Issue: Status check returns "Order not found"

**Cause:** Order ID mismatch or invitation not created

**Solution:**
1. Verify snap token was created successfully
2. Check `invitations` table for the order_id
3. Ensure order_id is passed correctly from frontend

### Issue: Status check always returns "pending"

**Cause:** Payment not actually completed in Midtrans

**Solution:**
1. Check Midtrans Dashboard for transaction status
2. Verify test card details are correct
3. Ensure 3DS authentication was completed
4. Check if payment expired (24 hour token expiry)

### Issue: CORS errors in browser console

**Cause:** Backend not configured to accept requests from frontend domain

**Solution:**
1. Add frontend domain to CORS allowed origins
2. Ensure `Access-Control-Allow-Origin` header is set
3. Check Laravel CORS middleware configuration

---

## Additional Resources

- [Midtrans Snap Documentation](https://docs.midtrans.com/en/snap/overview)
- [Midtrans API Reference](https://api-docs.midtrans.com/)
- [Laravel HTTP Client](https://laravel.com/docs/10.x/http-client)
- [Angular HttpClient](https://angular.io/guide/http)

---

## Changelog

### 2025-11-04
- **Added:** Payment status check endpoint (`POST /v1/midtrans/check-status`)
- **Added:** Frontend polling mechanism for pending payments
- **Fixed:** Database not updating when webhook goes to different environment
- **Improved:** Error handling and response messages
- **Updated:** API contract documentation with complete integration examples

---

## Support

For issues or questions:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check payment logs table: `SELECT * FROM payment_logs ORDER BY created_at DESC`
3. Review this documentation
4. Contact backend team with order_id and timestamp
