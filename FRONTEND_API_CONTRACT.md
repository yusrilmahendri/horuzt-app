# FRONTEND API CONTRACT - COMPLETE GUIDE

Complete API documentation for frontend developers implementing the wedding invitation SaaS application.

**Version:** 2.0
**Last Updated:** 2025-11-02
**Backend:** Laravel 10 + Sanctum
**Recommended Frontend:** Angular, React, Vue

---

## 📋 TABLE OF CONTENTS

1. [Base Configuration](#base-configuration)
2. [Authentication Flow](#authentication-flow)
3. [User Profile & Invitation Data](#user-profile--invitation-data)
4. [Package Management](#package-management)
5. [Payment Flow (Midtrans)](#payment-flow-midtrans)
6. [TypeScript Interfaces](#typescript-interfaces)
7. [Error Handling](#error-handling)
8. [Testing Guide](#testing-guide)

---

## 🔧 BASE CONFIGURATION

### API Base URL

```typescript
// environment.ts (Angular)
export const environment = {
  production: false,
  apiUrl: 'https://www.sena-digital.com/api',
  midtransClientKey: 'SB-Mid-client-NjshfjUODw5Zt75', // Sandbox
  // Production:
  // apiUrl: 'https://www.sena-digital.com/api',
  // midtransClientKey: 'Mid-client-xxxxxxxxxxxxx'
};
```

### HTTP Headers (All Authenticated Requests)

```typescript
const headers = {
  'Authorization': 'Bearer {sanctum_token}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
};
```

---

## 🔐 AUTHENTICATION FLOW

### 1. User Registration

**Endpoint:** `POST /v1/register`
**Auth Required:** No

**Request:**
```typescript
interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
}
```

**Example:**
```bash
curl -X POST https://www.sena-digital.com/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "secret123",
    "password_confirmation": "secret123",
    "phone": "08123456789"
  }'
```

**Response: 201 Created**
```json
{
  "token": "1|abc123def456ghi789...",
  "user": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08123456789",
    "created_at": "2025-11-02T10:00:00.000000Z"
  }
}
```

**Error: 422 Validation Failed**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

---

### 2. User Login

**Endpoint:** `POST /v1/login`
**Auth Required:** No

**Request:**
```typescript
interface LoginRequest {
  email: string;
  password: string;
}
```

**Example:**
```bash
curl -X POST https://www.sena-digital.com/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "secret123"
  }'
```

**Response: 200 OK**
```json
{
  "token": "2|xyz789abc456def123...",
  "user": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08123456789",
    "kode_pemesanan": "ORD-123"
  }
}
```

**Error: 401 Unauthorized**
```json
{
  "message": "Invalid credentials"
}
```

**Storage:**
```typescript
// Store token in localStorage or secure storage
localStorage.setItem('auth_token', response.token);
localStorage.setItem('user', JSON.stringify(response.user));
```

---

### 3. User Logout

**Endpoint:** `POST /v1/logout`
**Auth Required:** Yes

**Example:**
```bash
curl -X POST https://www.sena-digital.com/api/v1/logout \
  -H "Authorization: Bearer {token}"
```

**Response: 200 OK**
```json
{
  "message": "Logged out successfully"
}
```

**Cleanup:**
```typescript
// Remove token from storage
localStorage.removeItem('auth_token');
localStorage.removeItem('user');
```

---

## 👤 USER PROFILE & INVITATION DATA

### 1. Get User Profile with Invitation

**Endpoint:** `GET /v1/user-profile`
**Auth Required:** Yes
**Purpose:** Get complete user data including invitation and package details

**Example:**
```bash
curl -X GET https://www.sena-digital.com/api/v1/user-profile \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Response: 200 OK**
```json
{
  "data": {
    "id": 123,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "08123456789",
    "kode_pemesanan": "ORD-123",
    "created_at": "2025-10-01T10:00:00.000000Z",
    "invitation": {
      "id": 5,
      "user_id": 123,
      "paket_undangan_id": 3,
      "status": "step4",
      "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
      "midtrans_transaction_id": "midtrans-tx-1234567890",
      "payment_status": "paid",
      "domain_expires_at": "2026-11-02T12:00:00.000000Z",
      "payment_confirmed_at": "2025-11-02T12:00:00.000000Z",
      "package_price_snapshot": "299000.00",
      "package_duration_snapshot": 12,
      "created_at": "2025-10-01T10:00:00.000000Z",
      "updated_at": "2025-11-02T12:00:00.000000Z",
      "paket_undangan": {
        "id": 3,
        "name_paket": "Paket Platinum",
        "jenis_paket": "premium",
        "price": 299000,
        "masa_aktif": 12,
        "halaman_buku": "unlimited",
        "kirim_wa": true,
        "bebas_pilih_tema": true,
        "kirim_hadiah": true,
        "import_data": true,
        "created_at": "2025-01-01T00:00:00.000000Z"
      }
    }
  }
}
```

**Key Fields Explained:**

| Field | Type | Description |
|-------|------|-------------|
| `invitation.payment_status` | string | `pending`, `paid`, `failed`, `refunded` |
| `invitation.order_id` | string\|null | Midtrans order ID (set when payment initiated) |
| `invitation.midtrans_transaction_id` | string\|null | Set after payment confirmed |
| `invitation.payment_confirmed_at` | datetime\|null | When payment was confirmed |
| `invitation.domain_expires_at` | datetime\|null | When domain access expires |
| `paket_undangan.price` | integer | Package price in IDR (Rupiah) |
| `paket_undangan.masa_aktif` | integer | Duration in months |

---

## 📦 PACKAGE MANAGEMENT

### 1. Get Available Packages

**Endpoint:** `GET /v1/paket-undangan`
**Auth Required:** No (Public)
**Purpose:** Get list of all available wedding packages for selection

**Example:**
```bash
curl -X GET https://www.sena-digital.com/api/v1/paket-undangan \
  -H "Accept: application/json"
```

**Response: 200 OK**
```json
{
  "data": [
    {
      "id": 1,
      "name_paket": "Paket Basic",
      "jenis_paket": "basic",
      "price": 99000,
      "masa_aktif": 3,
      "halaman_buku": "50",
      "kirim_wa": false,
      "bebas_pilih_tema": false,
      "kirim_hadiah": false,
      "import_data": false
    },
    {
      "id": 2,
      "name_paket": "Paket Gold",
      "jenis_paket": "standard",
      "price": 199000,
      "masa_aktif": 6,
      "halaman_buku": "200",
      "kirim_wa": true,
      "bebas_pilih_tema": true,
      "kirim_hadiah": false,
      "import_data": true
    },
    {
      "id": 3,
      "name_paket": "Paket Platinum",
      "jenis_paket": "premium",
      "price": 299000,
      "masa_aktif": 12,
      "halaman_buku": "unlimited",
      "kirim_wa": true,
      "bebas_pilih_tema": true,
      "kirim_hadiah": true,
      "import_data": true
    }
  ]
}
```

**Use Case:**
- Display on pricing page
- Package selection during registration
- Compare features

---

## 💳 PAYMENT FLOW (MIDTRANS)

### 1. Create Snap Token (Initiate Payment)

**Endpoint:** `POST /midtrans/create-snap-token`
**Auth Required:** Yes
**Purpose:** Generate Midtrans Snap token to open payment popup

**Request:**
```typescript
interface CreateSnapTokenRequest {
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
```

**Example:**
```bash
curl -X POST https://www.sena-digital.com/api/midtrans/create-snap-token \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "invitation_id": 5,
    "amount": 299000,
    "customer_details": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
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
  }'
```

**Response: 201 Created**
```json
{
  "success": true,
  "data": {
    "snap_token": "66e4fa55-fdac-4ef9-91b5-733b97d1b862",
    "order_id": "INV-550e8400-e29b-41d4-a716-446655440000",
    "gross_amount": 299000,
    "invitation_id": 5,
    "expires_at": "2025-11-03T10:00:00Z"
  },
  "message": "Snap token created successfully"
}
```

**Validation Rules:**

| Field | Required | Rules |
|-------|----------|-------|
| `invitation_id` | Yes | Must exist and belong to authenticated user |
| `amount` | Yes | Min: 10,000, Max: 100,000,000 |
| `amount` | Yes | Must match package price exactly |
| `customer_details` | No | Optional customer information |
| `item_details` | No | Optional item breakdown |

**Error: 422 Validation Failed**

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

**Possible Validation Errors:**

1. **Already Paid:**
```json
{
  "errors": {
    "invitation_id": ["This invitation has already been paid."]
  }
}
```

2. **Payment Already Initiated:**
```json
{
  "errors": {
    "invitation_id": ["Payment already initiated for this invitation."]
  }
}
```

3. **Amount Mismatch:**
```json
{
  "errors": {
    "amount": ["Amount does not match package price."]
  }
}
```

4. **Invalid Invitation:**
```json
{
  "errors": {
    "invitation_id": ["Invalid invitation or you do not have permission to access this invitation."]
  }
}
```

**Error: 503 Service Unavailable**
```json
{
  "success": false,
  "message": "Failed to generate payment token. Please try again later."
}
```

---

### 2. Using Snap Token (Frontend)

After receiving snap_token, use Midtrans Snap.js to open payment popup:

**Include Snap.js in index.html:**
```html
<script
  type="text/javascript"
  src="https://app.sandbox.midtrans.com/snap/snap.js"
  data-client-key="SB-Mid-client-NjshfjUODw5Zt75">
</script>
```

**Open Payment Popup:**
```typescript
declare var snap: any;

snap.pay(snapToken, {
  onSuccess: function(result: any) {
    console.log('Payment success:', result);
    // Redirect to dashboard
    // Check payment status via GET /v1/user-profile
  },
  onPending: function(result: any) {
    console.log('Payment pending:', result);
    // Show pending message
    // User completed payment but waiting confirmation (bank transfer)
  },
  onError: function(result: any) {
    console.error('Payment error:', result);
    // Show error message
    // Allow user to retry
  },
  onClose: function() {
    console.log('Payment popup closed');
    // User closed popup without completing payment
  }
});
```

**Result Object Structure:**
```typescript
interface SnapPaymentResult {
  status_code: string;          // "200" for success
  status_message: string;        // "midtrans payment success"
  transaction_id: string;        // Midtrans transaction ID
  order_id: string;              // Your order ID (INV-xxx)
  gross_amount: string;          // "299000.00"
  payment_type: string;          // "credit_card", "bank_transfer", etc.
  transaction_time: string;      // "2025-11-02 10:30:00"
  transaction_status: string;    // "capture", "settlement", "pending"
  fraud_status?: string;         // "accept" (for credit card)
  // ... additional fields depending on payment method
}
```

---

### 3. Check Payment Status

After payment (via redirect or popup callback), check payment status:

**Endpoint:** `GET /v1/user-profile`
**Auth Required:** Yes

```typescript
// Poll status after redirect
function checkPaymentStatus() {
  fetch('https://www.sena-digital.com/api/v1/user-profile', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  })
  .then(res => res.json())
  .then(data => {
    const status = data.data.invitation.payment_status;

    if (status === 'paid') {
      // Payment confirmed! Show success
      console.log('✅ Payment confirmed');
      console.log('Expires:', data.data.invitation.domain_expires_at);
    } else if (status === 'pending') {
      // Still waiting for confirmation
      // Poll again in 3 seconds
      setTimeout(checkPaymentStatus, 3000);
    } else {
      // Payment failed
      console.error('❌ Payment failed');
    }
  });
}
```

---

## 📘 TYPESCRIPT INTERFACES

### Complete Interface Definitions

```typescript
// ==================== AUTH ====================

export interface RegisterRequest {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  kode_pemesanan?: string;
  created_at: string;
  updated_at?: string;
}

// ==================== PACKAGES ====================

export interface PaketUndangan {
  id: number;
  name_paket: string;
  jenis_paket: 'basic' | 'standard' | 'premium';
  price: number;
  masa_aktif: number;  // months
  halaman_buku: string | number;
  kirim_wa: boolean;
  bebas_pilih_tema: boolean;
  kirim_hadiah: boolean;
  import_data: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface PaketListResponse {
  data: PaketUndangan[];
}

// ==================== INVITATION ====================

export interface Invitation {
  id: number;
  user_id: number;
  paket_undangan_id: number;
  status: string;
  order_id: string | null;
  midtrans_transaction_id: string | null;
  payment_status: 'pending' | 'paid' | 'failed' | 'refunded';
  domain_expires_at: string | null;
  payment_confirmed_at: string | null;
  package_price_snapshot: string;
  package_duration_snapshot: number;
  package_features_snapshot?: any;
  created_at: string;
  updated_at: string;
  paket_undangan?: PaketUndangan;
}

export interface UserProfileResponse {
  data: {
    id: number;
    name: string;
    email: string;
    phone?: string;
    kode_pemesanan?: string;
    created_at: string;
    invitation?: Invitation;
  };
}

// ==================== PAYMENT ====================

export interface CustomerDetails {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
}

export interface ItemDetail {
  id: string;
  name: string;
  price: number;
  quantity: number;
}

export interface CreateSnapTokenRequest {
  invitation_id: number;
  amount: number;
  customer_details?: CustomerDetails;
  item_details?: ItemDetail[];
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

export interface SnapPaymentResult {
  status_code: string;
  status_message: string;
  transaction_id: string;
  order_id: string;
  gross_amount: string;
  payment_type: string;
  transaction_time: string;
  transaction_status: string;
  fraud_status?: string;
}

// ==================== ERROR ====================

export interface ValidationError {
  message: string;
  errors: {
    [key: string]: string[];
  };
}

export interface ApiError {
  success: false;
  message: string;
  errors?: {
    [key: string]: string[];
  };
}
```

---

## ⚠️ ERROR HANDLING

### HTTP Status Codes

| Code | Meaning | When | Action |
|------|---------|------|--------|
| 200 | OK | Request successful | Process response |
| 201 | Created | Resource created (snap token) | Use snap_token |
| 401 | Unauthorized | Invalid/missing token | Redirect to login |
| 403 | Forbidden | No permission | Show error |
| 404 | Not Found | Resource not found | Show error |
| 422 | Validation Error | Invalid input | Show validation errors |
| 500 | Server Error | Backend error | Show generic error |
| 503 | Service Unavailable | Midtrans API down | Show retry message |

---

### Error Response Formats

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken."],
    "amount": ["Amount does not match package price."]
  }
}
```

**Generic Error (500):**
```json
{
  "success": false,
  "message": "An unexpected error occurred. Please try again later."
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated"
}
```

---

### Error Handling Example (TypeScript)

```typescript
async function createPayment(request: CreateSnapTokenRequest) {
  try {
    const response = await fetch('/api/midtrans/create-snap-token', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(request)
    });

    const data = await response.json();

    if (!response.ok) {
      if (response.status === 422) {
        // Validation errors
        const errors = data.errors;
        Object.keys(errors).forEach(field => {
          console.error(`${field}: ${errors[field].join(', ')}`);
        });
        throw new Error('Validation failed');
      } else if (response.status === 401) {
        // Unauthorized - redirect to login
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
        throw new Error('Session expired');
      } else if (response.status === 503) {
        // Service unavailable
        throw new Error('Payment service temporarily unavailable. Please try again.');
      } else {
        // Generic error
        throw new Error(data.message || 'An error occurred');
      }
    }

    return data as SnapTokenResponse;

  } catch (error) {
    console.error('Payment creation failed:', error);
    throw error;
  }
}
```

---

## 🧪 TESTING GUIDE

### 1. Test Credentials

**Sandbox Login:**
```
Email: tas@gmail.com
Password: 123123
```

**Midtrans Test Cards:**

**✅ Success:**
```
Card Number: 4811 1111 1111 1114
CVV: 123
Expiry: 01/25
OTP: 112233
```

**❌ Failure:**
```
Card Number: 4911 1111 1111 1113
CVV: 123
Expiry: 01/25
```

---

### 2. Complete Flow Test

```typescript
// 1. Login
const loginResponse = await fetch('/api/v1/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'tas@gmail.com',
    password: '123123'
  })
});
const { token, user } = await loginResponse.json();

// 2. Get user profile
const profileResponse = await fetch('/api/v1/user-profile', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const { data } = await profileResponse.json();
const invitation = data.invitation;

// 3. Create snap token
const paymentRequest = {
  invitation_id: invitation.id,
  amount: invitation.paket_undangan.price,
  customer_details: {
    first_name: user.name.split(' ')[0],
    last_name: user.name.split(' ').slice(1).join(' '),
    email: user.email,
    phone: user.phone || '08123456789'
  }
};

const snapResponse = await fetch('/api/midtrans/create-snap-token', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(paymentRequest)
});
const { data: snapData } = await snapResponse.json();

// 4. Open Snap.js
snap.pay(snapData.snap_token, {
  onSuccess: (result) => {
    console.log('✅ Payment success:', result);
  }
});

// 5. Check status after payment
const statusResponse = await fetch('/api/v1/user-profile', {
  headers: { 'Authorization': `Bearer ${token}` }
});
const { data: updatedData } = await statusResponse.json();
console.log('Payment status:', updatedData.invitation.payment_status);
```

---

## 📌 IMPORTANT NOTES

### 1. Security

- ⚠️ **Never trust client-side payment confirmation alone**
- Always verify `payment_status` from `GET /v1/user-profile`
- Store token securely (HttpOnly cookies recommended for web)
- Implement CSRF protection for forms
- Use HTTPS in production

### 2. Payment Status Flow

```
pending → (user pays) → paid
pending → (user cancels) → pending (unchanged)
pending → (payment fails) → failed
paid → (refund issued) → refunded
```

### 3. Webhook vs Frontend Callbacks

- **Webhook** = Backend receives notification (authoritative source)
- **Frontend callbacks** = User feedback only (can be manipulated)
- **Always verify** payment status from backend API

### 4. Polling Strategy

After payment redirect:
- Poll `/v1/user-profile` every 3 seconds
- Maximum 10 attempts (30 seconds total)
- Stop when `payment_status === 'paid'`

### 5. Package Price Snapshot

When payment is initiated:
- Package price is snapshotted in `invitation.package_price_snapshot`
- Even if admin changes package price later, user pays snapshot price
- This ensures pricing consistency

---

## 📚 ADDITIONAL RESOURCES

- **Midtrans Snap.js Docs:** https://snap-docs.midtrans.com
- **Midtrans API Reference:** https://api-docs.midtrans.com
- **Laravel Sanctum:** https://laravel.com/docs/10.x/sanctum
- **Backend Setup Guide:** `MIDTRANS_DASHBOARD_SETUP.md`
- **Testing Guide:** `MIDTRANS_API_TESTING.md`

---

## 🔄 CHANGELOG

### Version 2.0 (2025-11-02)
- Complete rewrite for frontend developers
- Added TypeScript interfaces
- Added comprehensive error handling
- Added payment flow examples
- Added testing guide

---

**Questions?** Check backend logs or contact backend team.

**Ready to implement?** See `FRONTEND_INVOICE_IMPLEMENTATION.md` for UI implementation guide.
