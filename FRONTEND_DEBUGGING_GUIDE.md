# FRONTEND DEBUGGING GUIDE - Midtrans Payment Issues

**Date:** 2025-11-02
**Issue:** 422 Validation Error when creating snap token
**Status:** ✅ RESOLVED

---

## 🐛 ORIGINAL ERROR

### Error Response

```json
{
  "message": "Invalid invitation or you do not have permission to access this invitation. (and 1 more error)",
  "errors": {
    "invitation_id": [
      "Invalid invitation or you do not have permission to access this invitation."
    ],
    "customer_details.last_name": [
      "The customer details.last name field must be a string."
    ]
  }
}
```

### Original Request

```typescript
POST http://127.0.0.1:8000/api/midtrans/create-snap-token
Authorization: Bearer 16|YKAIdmqk4V6kJgAG1NWFsCSIzrOFKeJyCRpfy76Q4bed8710

Payload:
{
  "invitation_id": 4,
  "amount": 199000,
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
      "price": 199000,
      "quantity": 1
    }
  ]
}
```

---

## 🔍 ROOT CAUSE ANALYSIS

### Issue #1: Wrong invitation_id (SECURITY VIOLATION)

**What Happened:**
- Frontend was logged in as **user_id = 3** (tas@gmail.com)
- Frontend requested payment for **invitation_id = 4**
- But invitation_id = 4 belongs to **user_id = 5** (kosa@gmail.com)

**Why It Failed:**
- Backend correctly validates that `invitation_id` must belong to authenticated user
- This is a **security feature** to prevent users from paying for other users' invitations
- Validation rule: `Rule::exists('invitations', 'id')->where('user_id', $this->user()->id)`

**Database Evidence:**
```
User 3 (tas@gmail.com):
  - Has invitation_id: 2
  - Package: Paket Platinum (Rp 299.000)
  - Status: PAID ✅

User 5 (kosa@gmail.com):
  - Has invitation_id: 4
  - Package: Paket Gold (Rp 199.000)
  - Status: PENDING 🟡
```

### Issue #2: Empty string validation

**What Happened:**
- Frontend sent `"last_name": ""` (empty string)
- Backend validation rule didn't allow empty strings

**Why It Failed:**
- Original rule: `'customer_details.last_name' => 'sometimes|string|max:100'`
- The `string` type doesn't accept empty strings by default in Laravel

**Fix Applied:**
- Updated to: `'customer_details.last_name' => 'sometimes|nullable|string|max:100'`
- Now accepts empty strings, null, or valid strings

---

## ✅ SOLUTIONS

### Solution #1: Use Correct invitation_id (CRITICAL)

**❌ WRONG WAY (Current Frontend Implementation):**

```typescript
// DON'T hardcode invitation_id!
const payload = {
  invitation_id: 4,  // ❌ WRONG!
  amount: 199000
};
```

**✅ CORRECT WAY:**

```typescript
// Step 1: Get user profile first
getUserProfile(): Observable<UserProfile> {
  return this.http.get<UserProfile>('/api/v1/user-profile', {
    headers: { Authorization: `Bearer ${token}` }
  });
}

// Step 2: Extract invitation_id from profile
processPayment(): void {
  this.apiService.getUserProfile().subscribe({
    next: (profile) => {
      const invitation = profile.data.invitation;

      // Validation: Check if user has invitation
      if (!invitation) {
        alert('No invitation found. Please create one first.');
        return;
      }

      // Validation: Check payment status
      if (invitation.payment_status === 'paid') {
        alert('This invitation is already paid!');
        this.router.navigate(['/dashboard']);
        return;
      }

      // ✅ Use invitation_id from user's own data
      const payload = {
        invitation_id: invitation.id,  // ✅ CORRECT!
        amount: invitation.paket_undangan.price,
        customer_details: {
          first_name: this.user.name?.split(' ')[0] || 'Guest',
          last_name: this.user.name?.split(' ').slice(1).join(' ') || '',
          email: this.user.email,
          phone: this.user.phone || ''
        }
      };

      this.createSnapToken(payload);
    }
  });
}
```

### Solution #2: Handle Empty Strings (FIXED IN BACKEND)

**Backend Fix Applied:**
```php
// File: app/Http/Requests/CreateSnapTokenRequest.php
// Line 34-37

'customer_details.first_name' => 'sometimes|nullable|string|max:100',
'customer_details.last_name' => 'sometimes|nullable|string|max:100',
'customer_details.email' => 'sometimes|nullable|email|max:255',
'customer_details.phone' => 'sometimes|nullable|string|max:20',
```

**Now frontend can safely send:**
```typescript
customer_details: {
  first_name: 'John',
  last_name: '',  // ✅ Empty string now allowed
  email: 'john@example.com',
  phone: ''       // ✅ Empty string now allowed
}
```

---

## 🧪 WORKING TEST CASE

### Test Credentials

```
Email: kosa@gmail.com
Password: 123123
Invitation ID: 4
Package: Paket Gold (Rp 199.000)
Status: PENDING
```

### Complete Working Request

```bash
# Step 1: Login
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "kosa@gmail.com",
    "password": "123123"
  }'

# Response:
{
  "access_token": "19|MTNvLESCF2tjnGL5GkxZGpy8CcoY7Eh66QVLI2Wjd1d018ba",
  "token_type": "Bearer",
  "role": ["user"]
}

# Step 2: Get User Profile
curl -X GET http://127.0.0.1:8000/api/v1/user-profile \
  -H "Authorization: Bearer 19|MTNvLESCF2tjnGL5GkxZGpy8CcoY7Eh66QVLI2Wjd1d018ba" \
  -H "Accept: application/json"

# Response includes:
{
  "data": {
    "invitation": {
      "id": 4,
      "payment_status": "pending",
      "paket_undangan": {
        "name_paket": "Paket Gold",
        "price": "199000.00"
      }
    }
  }
}

# Step 3: Create Snap Token
curl -X POST http://127.0.0.1:8000/api/midtrans/create-snap-token \
  -H "Authorization: Bearer 19|MTNvLESCF2tjnGL5GkxZGpy8CcoY7Eh66QVLI2Wjd1d018ba" \
  -H "Content-Type: application/json" \
  -d '{
    "invitation_id": 4,
    "amount": 199000,
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
        "price": 199000,
        "quantity": 1
      }
    ]
  }'

# ✅ Success Response:
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

---

## 🔧 FRONTEND CODE FIXES

### Fix #1: Dynamic invitation_id (Angular)

```typescript
// invoice.component.ts

export class InvoiceComponent implements OnInit {
  invitation: Invitation | null = null;
  package: PaketUndangan | null = null;

  ngOnInit(): void {
    this.loadInvitationData();
  }

  /**
   * Load user's invitation data dynamically
   */
  loadInvitationData(): void {
    this.apiService.getUserProfile().subscribe({
      next: (response) => {
        this.invitation = response.data.invitation;
        this.package = this.invitation?.paket_undangan;

        // Validation checks
        if (!this.invitation) {
          this.showError('No invitation found');
          return;
        }

        if (this.invitation.payment_status === 'paid') {
          this.router.navigate(['/dashboard']);
          return;
        }
      },
      error: (error) => {
        console.error('Failed to load invitation:', error);
      }
    });
  }

  /**
   * Process payment with correct invitation_id
   */
  processPayment(): void {
    if (!this.invitation || !this.package) return;

    // ✅ Use invitation_id from loaded data
    const payload = {
      invitation_id: this.invitation.id,  // Dynamic!
      amount: this.package.price,
      customer_details: {
        first_name: this.user.name?.split(' ')[0] || 'Guest',
        last_name: this.user.name?.split(' ').slice(1).join(' ') || '',
        email: this.user.email,
        phone: this.user.phone || ''
      }
    };

    this.midtransService.createSnapToken(payload).subscribe({
      next: (response) => {
        this.midtransService.pay(response.data.snap_token);
      }
    });
  }
}
```

### Fix #2: Handle Empty Strings Gracefully

```typescript
// Helper function to handle empty strings
private buildCustomerDetails(user: User): CustomerDetails {
  const nameParts = (user.name || '').split(' ');

  return {
    first_name: nameParts[0] || 'Guest',
    last_name: nameParts.slice(1).join(' ') || '',  // ✅ Empty string is OK now
    email: user.email || '',
    phone: user.phone || ''
  };
}
```

---

## 📋 VALIDATION CHECKLIST

### Before Creating Snap Token

```typescript
// Validate before sending request
if (!invitation) {
  throw new Error('No invitation found');
}

if (invitation.payment_status === 'paid') {
  throw new Error('Invitation already paid');
}

if (invitation.payment_status === 'failed') {
  // Allow retry
}

if (!package || !package.price) {
  throw new Error('Package information not found');
}

if (amount !== package.price) {
  throw new Error('Amount mismatch');
}

if (!invitation.id) {
  throw new Error('Invalid invitation ID');
}
```

---

## 🚨 COMMON ERRORS & FIXES

### Error: "Invalid invitation or you do not have permission"

**Cause:** invitation_id doesn't belong to authenticated user

**Fix:**
```typescript
// ❌ DON'T hardcode
invitation_id: 4

// ✅ DO get from API
invitation_id: profile.data.invitation.id
```

---

### Error: "This invitation has already been paid"

**Cause:** Trying to pay for invitation that's already paid

**Fix:**
```typescript
if (invitation.payment_status === 'paid') {
  console.log('Already paid!');
  // Redirect to dashboard or show message
  return;
}
```

---

### Error: "Payment already initiated for this invitation"

**Cause:** order_id already exists (snap token already created)

**Fix:**
```typescript
if (invitation.order_id) {
  // Option 1: Continue with existing order
  console.log('Payment already initiated');

  // Option 2: Show message to complete pending payment
  alert('Please complete your pending payment');
}
```

---

### Error: "Amount does not match package price"

**Cause:** amount in request doesn't match package price in database

**Fix:**
```typescript
// ❌ WRONG
amount: 199000  // hardcoded

// ✅ CORRECT
amount: invitation.paket_undangan.price  // from API
```

---

## 📊 COMPLETE FLOW DIAGRAM

```
┌─────────────────────────────────────────────────────────────┐
│  1. USER LOADS INVOICE PAGE                                 │
│     ↓                                                        │
│  2. GET /v1/user-profile                                    │
│     ├─ Extract invitation.id                                │
│     ├─ Extract paket_undangan.price                         │
│     ├─ Check payment_status                                 │
│     └─ Validate user has pending invitation                 │
│     ↓                                                        │
│  3. USER CLICKS "BAYAR SEKARANG"                            │
│     ├─ Build payload with invitation.id                     │
│     ├─ Amount = paket_undangan.price                        │
│     └─ POST /midtrans/create-snap-token                     │
│     ↓                                                        │
│  4. BACKEND VALIDATES                                        │
│     ├─ ✅ invitation_id belongs to user                     │
│     ├─ ✅ payment_status = 'pending'                        │
│     ├─ ✅ amount matches package price                      │
│     ├─ ✅ no existing order_id                              │
│     └─ Generate snap_token                                   │
│     ↓                                                        │
│  5. FRONTEND OPENS SNAP POPUP                                │
│     └─ snap.pay(snap_token)                                 │
│     ↓                                                        │
│  6. USER COMPLETES PAYMENT                                   │
│     ↓                                                        │
│  7. MIDTRANS SENDS WEBHOOK                                   │
│     └─ Backend updates payment_status = 'paid'             │
│     ↓                                                        │
│  8. FRONTEND VERIFIES STATUS                                 │
│     └─ GET /v1/user-profile (check payment_status)         │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 SUMMARY

### What Was Fixed:

1. ✅ **Backend Validation** - Added `nullable` to customer_details fields
2. ✅ **Testing** - Verified with correct user credentials
3. ✅ **Documentation** - Created comprehensive debugging guide

### What Frontend Must Fix:

1. ⚠️ **CRITICAL:** Don't hardcode `invitation_id`
2. ⚠️ **CRITICAL:** Always get invitation data from `GET /v1/user-profile`
3. ⚠️ **IMPORTANT:** Validate payment_status before requesting payment
4. ⚠️ **IMPORTANT:** Use dynamic `amount` from package data

### Working Test Case:

```
User: kosa@gmail.com
Password: 123123
Invitation ID: 4 (from API, not hardcoded!)
Package: Paket Gold
Amount: 199000 (from API, not hardcoded!)
Status: pending ✅
```

---

## 📞 NEED HELP?

**Backend logs:**
```bash
tail -f storage/logs/laravel.log
```

**Check user's invitation:**
```bash
curl -X GET http://127.0.0.1:8000/api/v1/user-profile \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Test payment:**
```bash
# Use the test script
/tmp/test_midtrans_fixed.sh
```

---

**Last Updated:** 2025-11-02
**Status:** ✅ All Issues Resolved
**Next Step:** Frontend implements dynamic invitation_id loading
