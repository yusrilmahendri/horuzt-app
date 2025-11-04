# PUBLIC PAYMENT API - Query Parameter Version

**Updated:** 2025-11-02
**Version:** 2.0 - Public Payment (No Authentication Required)

---

## 🔄 MAJOR CHANGE: Authentication Removed

The payment API endpoint now uses **query parameter `user_id`** instead of Bearer token authentication.

### Why This Change?

- Payment page is now **PUBLIC** (no login required)
- Users can pay directly via URL without authentication
- Suitable for payment links sent via email/WhatsApp
- Simpler integration for frontend

---

## 🎯 NEW ENDPOINT SPECIFICATION

### Create Snap Token (Public)

**Endpoint:** `POST /api/midtrans/create-snap-token?user_id={user_id}`

**Authentication:** ❌ None (Public endpoint)

**Query Parameters:**
- `user_id` (required): The ID of the user making the payment

---

## 📝 API USAGE

### URL Format

```
POST http://127.0.0.1:8000/api/midtrans/create-snap-token?user_id=5
```

**Important:**
- `user_id` must be passed as **query parameter**
- No Bearer token needed
- No authentication headers required

---

## 🔧 REQUEST EXAMPLES

### Example 1: Basic Request

```bash
curl -X POST "http://127.0.0.1:8000/api/midtrans/create-snap-token?user_id=5" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "invitation_id": 4,
    "amount": 199000,
    "customer_details": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "phone": "08123456789"
    },
    "item_details": [
      {
        "id": "paket-2",
        "name": "Paket Gold",
        "price": 199000,
        "quantity": 1
      }
    ]
  }'
```

### Example 2: Minimal Request (without optional fields)

```bash
curl -X POST "http://127.0.0.1:8000/api/midtrans/create-snap-token?user_id=5" \
  -H "Content-Type: application/json" \
  -d '{
    "invitation_id": 4,
    "amount": 199000
  }'
```

---

## 📥 REQUEST BODY

### Required Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `invitation_id` | integer | Yes | Invitation ID that belongs to the user |
| `amount` | numeric | Yes | Payment amount (must match package price) |

### Optional Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `customer_details` | object | No | Customer information |
| `customer_details.first_name` | string | No | First name (max 100 chars) |
| `customer_details.last_name` | string | No | Last name (max 100 chars, can be empty) |
| `customer_details.email` | string | No | Email address |
| `customer_details.phone` | string | No | Phone number (max 20 chars) |
| `item_details` | array | No | Item breakdown |
| `item_details[].id` | string | No | Item ID |
| `item_details[].name` | string | No | Item name |
| `item_details[].price` | numeric | No | Item price |
| `item_details[].quantity` | integer | No | Item quantity |

---

## ✅ SUCCESS RESPONSE

### Status Code: 201 Created

```json
{
  "success": true,
  "data": {
    "snap_token": "4b3ae40c-b22b-4a9a-bde7-cf1f13af6194",
    "order_id": "INV-6d35e8f5-a9db-4a59-89b2-1c005cf12da4",
    "gross_amount": 199000,
    "invitation_id": 4,
    "expires_at": "2025-11-03T16:34:52+00:00"
  },
  "message": "Snap token created successfully"
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `snap_token` | string | Token for Midtrans Snap popup (use with snap.pay()) |
| `order_id` | string | Unique order ID (INV-{uuid}) |
| `gross_amount` | integer | Total payment amount |
| `invitation_id` | integer | Related invitation ID |
| `expires_at` | datetime | Token expiration time (24 hours) |

---

## ❌ ERROR RESPONSES

### Error 1: Missing user_id Query Parameter

**Status Code:** 422

```json
{
  "message": "User ID is required in query parameter (?user_id=X)",
  "errors": {
    "user_id": [
      "User ID is required in query parameter (?user_id=X)"
    ]
  }
}
```

**Solution:** Add `?user_id=X` to the URL

---

### Error 2: Invalid invitation_id

**Status Code:** 422

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "invitation_id": [
      "Invalid invitation or you do not have permission to access this invitation."
    ]
  }
}
```

**Cause:**
- invitation_id doesn't exist
- invitation_id doesn't belong to the specified user_id

**Solution:** Use correct invitation_id that belongs to the user

---

### Error 3: Already Paid

**Status Code:** 422

```json
{
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "This invitation has already been paid."
    ]
  }
}
```

**Cause:** The invitation payment_status is already "paid"

**Solution:** Cannot pay again. Redirect user to dashboard.

---

### Error 4: Payment Already Initiated

**Status Code:** 422

```json
{
  "message": "Validation failed",
  "errors": {
    "invitation_id": [
      "Payment already initiated for this invitation."
    ]
  }
}
```

**Cause:** order_id already exists (snap token already created)

**Solution:** User should complete the existing payment or contact support.

---

### Error 5: Amount Mismatch

**Status Code:** 422

```json
{
  "message": "Validation failed",
  "errors": {
    "amount": [
      "Amount does not match package price."
    ]
  }
}
```

**Cause:** amount in request doesn't match package price in database

**Solution:** Use exact package price from database

---

### Error 6: User Not Found

**Status Code:** 404

```json
{
  "message": "No query results for model [App\\Models\\User] 999"
}
```

**Cause:** user_id doesn't exist in database

**Solution:** Use valid user_id

---

## 🅰️ ANGULAR IMPLEMENTATION

### Service Method

```typescript
// midtrans.service.ts

import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../environments/environment';

export interface CreateSnapTokenRequest {
  invitation_id: number;
  amount: number;
  customer_details?: {
    first_name: string;
    last_name: string;
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

@Injectable({
  providedIn: 'root'
})
export class MidtransService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /**
   * Create Snap Token using user_id query parameter
   * NO AUTHENTICATION REQUIRED
   */
  createSnapToken(userId: number, payload: CreateSnapTokenRequest): Observable<SnapTokenResponse> {
    // Build URL with user_id query parameter
    const params = new HttpParams().set('user_id', userId.toString());

    return this.http.post<SnapTokenResponse>(
      `${this.apiUrl}/midtrans/create-snap-token`,
      payload,
      {
        params,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      }
    );
  }
}
```

---

### Component Usage

```typescript
// payment.component.ts

export class PaymentComponent implements OnInit {
  userId: number;
  invitation: any;

  constructor(
    private route: ActivatedRoute,
    private midtransService: MidtransService
  ) {}

  ngOnInit(): void {
    // Get user_id from route parameter or somewhere else
    this.userId = +this.route.snapshot.paramMap.get('userId')!;

    // Load invitation data
    this.loadInvitationData();
  }

  loadInvitationData(): void {
    // Fetch invitation data for this user
    // This should be a public endpoint too
    this.apiService.getPublicInvitation(this.userId).subscribe({
      next: (data) => {
        this.invitation = data.invitation;
      }
    });
  }

  processPayment(): void {
    const payload: CreateSnapTokenRequest = {
      invitation_id: this.invitation.id,
      amount: this.invitation.paket_undangan.price,
      customer_details: {
        first_name: this.invitation.user.name?.split(' ')[0] || 'Guest',
        last_name: this.invitation.user.name?.split(' ').slice(1).join(' ') || '',
        email: this.invitation.user.email,
        phone: this.invitation.user.phone || ''
      },
      item_details: [
        {
          id: `paket-${this.invitation.paket_undangan_id}`,
          name: this.invitation.paket_undangan.name_paket,
          price: this.invitation.paket_undangan.price,
          quantity: 1
        }
      ]
    };

    // ✅ Pass user_id as first parameter
    this.midtransService.createSnapToken(this.userId, payload).subscribe({
      next: (response) => {
        console.log('✅ Snap token created:', response.data.snap_token);

        // Open Snap popup
        snap.pay(response.data.snap_token, {
          onSuccess: (result) => {
            console.log('Payment success!', result);
            window.location.href = '/payment-success';
          },
          onError: (result) => {
            console.error('Payment failed!', result);
            alert('Payment failed. Please try again.');
          }
        });
      },
      error: (error) => {
        console.error('❌ Failed to create snap token:', error);

        if (error.error?.errors) {
          const errorMessages = Object.values(error.error.errors).flat();
          alert(errorMessages.join('\n'));
        }
      }
    });
  }
}
```

---

## ⚛️ REACT IMPLEMENTATION

```typescript
// useMidtrans.ts

import { useState } from 'react';
import axios from 'axios';

const API_URL = 'http://127.0.0.1:8000/api';

interface SnapTokenData {
  snap_token: string;
  order_id: string;
  gross_amount: number;
  invitation_id: number;
  expires_at: string;
}

export const useMidtrans = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const createSnapToken = async (
    userId: number,
    invitationId: number,
    amount: number
  ): Promise<SnapTokenData | null> => {
    setLoading(true);
    setError(null);

    try {
      // ✅ user_id in query parameter
      const response = await axios.post(
        `${API_URL}/midtrans/create-snap-token?user_id=${userId}`,
        {
          invitation_id: invitationId,
          amount: amount
        },
        {
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        }
      );

      setLoading(false);
      return response.data.data;
    } catch (err: any) {
      setLoading(false);

      if (err.response?.data?.errors) {
        const errors = Object.values(err.response.data.errors).flat();
        setError(errors.join(', '));
      } else {
        setError(err.message || 'Failed to create payment token');
      }

      return null;
    }
  };

  return { createSnapToken, loading, error };
};

// Usage in component
const PaymentPage = ({ userId, invitationId, amount }) => {
  const { createSnapToken, loading, error } = useMidtrans();

  const handlePayment = async () => {
    const snapData = await createSnapToken(userId, invitationId, amount);

    if (snapData) {
      // Open Snap popup
      (window as any).snap.pay(snapData.snap_token, {
        onSuccess: () => {
          window.location.href = '/payment-success';
        }
      });
    }
  };

  return (
    <button onClick={handlePayment} disabled={loading}>
      {loading ? 'Processing...' : 'Pay Now'}
    </button>
  );
};
```

---

## 🌐 VANILLA JAVASCRIPT

```javascript
// Simple vanilla JS example

async function createPayment(userId, invitationId, amount) {
  try {
    const response = await fetch(
      `http://127.0.0.1:8000/api/midtrans/create-snap-token?user_id=${userId}`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          invitation_id: invitationId,
          amount: amount
        })
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.message || 'Failed to create payment');
    }

    // Open Snap popup
    snap.pay(data.data.snap_token, {
      onSuccess: function(result) {
        console.log('Payment success!', result);
        window.location.href = '/payment-success';
      },
      onError: function(result) {
        console.error('Payment failed!', result);
        alert('Payment failed. Please try again.');
      }
    });

  } catch (error) {
    console.error('Error:', error);
    alert(error.message);
  }
}

// Usage
document.getElementById('payButton').addEventListener('click', () => {
  const userId = 5;  // Get from URL or data attribute
  const invitationId = 4;
  const amount = 199000;

  createPayment(userId, invitationId, amount);
});
```

---

## 🔗 PUBLIC PAYMENT URL PATTERN

### Recommended URL Structure

```
https://yourdomain.com/payment/{user_id}
https://yourdomain.com/payment/{user_id}/{invitation_id}
https://yourdomain.com/invoice/{user_id}
```

### Example URLs

```
https://yourdomain.com/payment/5
https://yourdomain.com/payment/5/4
https://yourdomain.com/invoice/5
```

### Frontend Route Configuration (Angular)

```typescript
// app-routing.module.ts

const routes: Routes = [
  {
    path: 'payment/:userId',
    component: PaymentComponent
  },
  {
    path: 'payment/:userId/:invitationId',
    component: PaymentComponent
  }
];

// payment.component.ts
ngOnInit() {
  this.userId = +this.route.snapshot.paramMap.get('userId')!;
  this.invitationId = +this.route.snapshot.paramMap.get('invitationId')!;
}
```

---

## 📊 COMPLETE FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────────────┐
│  PUBLIC PAYMENT FLOW (No Authentication)                    │
└─────────────────────────────────────────────────────────────┘

1. USER OPENS PUBLIC PAYMENT URL
   https://yourdomain.com/payment/5
        ↓
2. FRONTEND EXTRACTS user_id FROM URL
   user_id = 5
        ↓
3. FRONTEND LOADS INVITATION DATA
   GET /api/public/user/{user_id}/invitation
   (This should be a public endpoint too)
        ↓
4. USER CLICKS "PAY NOW" BUTTON
        ↓
5. FRONTEND CALLS API WITH user_id QUERY PARAM
   POST /api/midtrans/create-snap-token?user_id=5
   Body: { invitation_id: 4, amount: 199000 }
   ❌ No Bearer token needed!
        ↓
6. BACKEND VALIDATES
   ✅ user_id exists
   ✅ invitation_id belongs to user_id
   ✅ payment_status = 'pending'
   ✅ amount matches package price
        ↓
7. BACKEND GENERATES SNAP TOKEN
   Returns: { snap_token: "xxx", order_id: "INV-xxx" }
        ↓
8. FRONTEND OPENS SNAP POPUP
   snap.pay(snap_token)
        ↓
9. USER COMPLETES PAYMENT
        ↓
10. MIDTRANS SENDS WEBHOOK
    Backend updates payment_status = 'paid'
        ↓
11. USER REDIRECTED TO SUCCESS PAGE
```

---

## ✅ VALIDATION CHECKLIST

Before calling the API, ensure:

```typescript
// Validation checks
if (!userId || userId <= 0) {
  throw new Error('Invalid user ID');
}

if (!invitationId || invitationId <= 0) {
  throw new Error('Invalid invitation ID');
}

if (!amount || amount < 10000) {
  throw new Error('Invalid amount');
}

// Build URL with query parameter
const url = `${apiUrl}/midtrans/create-snap-token?user_id=${userId}`;
```

---

## 🚨 COMMON ERRORS

### Error: "User ID is required in query parameter"

```typescript
// ❌ WRONG
POST /api/midtrans/create-snap-token
Body: { user_id: 5, ... }

// ✅ CORRECT
POST /api/midtrans/create-snap-token?user_id=5
Body: { invitation_id: 4, ... }
```

### Error: "Invalid invitation"

```typescript
// Make sure invitation_id belongs to user_id
// Query parameter user_id must match invitation owner
```

---

## 🔒 SECURITY NOTES

### Current Implementation

- ✅ Validates invitation belongs to specified user
- ✅ Validates payment status
- ✅ Validates amount matches package price
- ✅ Prevents double payment

### Limitations

- ⚠️ Anyone with user_id can initiate payment
- ⚠️ No rate limiting on public endpoint
- ⚠️ Consider adding CAPTCHA for production

### Recommendations

1. Add rate limiting:
```php
Route::post('/midtrans/create-snap-token', ...)
    ->middleware('throttle:10,1'); // 10 requests per minute
```

2. Add CAPTCHA verification for production

3. Log all payment attempts

---

## 📞 NEED HELP?

**Test the API:**
```bash
curl -X POST "http://127.0.0.1:8000/api/midtrans/create-snap-token?user_id=5" \
  -H "Content-Type: application/json" \
  -d '{"invitation_id": 4, "amount": 199000}'
```

**Check logs:**
```bash
tail -f storage/logs/laravel.log
```

---

## 📋 SUMMARY OF CHANGES

### What Changed:

1. ❌ **Removed:** Bearer token authentication
2. ✅ **Added:** `user_id` query parameter
3. ✅ **Changed:** URL format includes `?user_id=X`
4. ✅ **Benefit:** Public payment page (no login required)

### Migration Guide:

**Old Way (With Bearer Token):**
```typescript
POST /api/midtrans/create-snap-token
Headers: { Authorization: "Bearer {token}" }
Body: { invitation_id: 4, amount: 199000 }
```

**New Way (With Query Parameter):**
```typescript
POST /api/midtrans/create-snap-token?user_id=5
Headers: { Content-Type: "application/json" }
Body: { invitation_id: 4, amount: 199000 }
```

---

**Last Updated:** 2025-11-02
**Version:** 2.0 - Public Payment
**Status:** ✅ Ready for Frontend Implementation
