# API Contract Documentation - Admin Payment Management

## Overview
This API contract covers the complete payment management system from the admin perspective, including bank account (rekening) management, Tripay payment gateway integration, and Midtrans payment gateway integration. This system supports CRUD operations for all payment methods and provides comprehensive payment method configuration.

**Base URL**: `/api/v1/admin`  
**Authentication**: Required (Sanctum Token)  
**Authorization**: Admin role required  
**Content-Type**: `application/json` (except file uploads)

---

## Business Process Summary

1. **Payment Methods Management**: Admin can manage various payment methods including Manual (Bank Transfer), Tripay, and Midtrans
2. **Bank Account Management**: Complete CRUD operations for admin bank accounts used for manual transfers
3. **Tripay Integration**: Configuration and management of Tripay payment gateway settings
4. **Midtrans Integration**: Configuration and management of Midtrans payment gateway settings
5. **Package Management**: Admin can manage wedding invitation packages and their pricing
6. **Unified Payment Data**: Retrieve all payment methods data in a single API call

---

## Authentication

### Header Requirements
```
Authorization: Bearer {sanctum_token}
Accept: application/json
Content-Type: application/json
```

### Error Response (401 Unauthorized)
```json
{
    "message": "Unauthenticated."
}
```

### Error Response (403 Forbidden)
```json
{
    "message": "This action is unauthorized."
}
```

---

## API Endpoints

## 1. REKENING (BANK ACCOUNT) MANAGEMENT

### 1.1. List Admin Bank Accounts

**Endpoint**: `GET /api/v1/admin/get-rekening`

**Purpose**: Retrieve all bank accounts managed by admin

**Request Example**:
```
GET /api/v1/admin/get-rekening
```

**Success Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "email": "admin@example.com",
            "methode_pembayaran": "Manual",
            "id_methode_pembayaran": "1",
            "nama_bank": "Bank Central Asia",
            "kode_bank": "014",
            "nomor_rekening": "1234567890",
            "nama_pemilik": "Admin User",
            "photo_rek": "http://localhost/storage/photos/rek-photo.jpg",
            "created_at": "2025-09-10T10:00:00.000000Z",
            "updated_at": "2025-09-10T10:00:00.000000Z",
            "bank": {
                "id": 1,
                "kode_bank": "014",
                "name": "Bank Central Asia"
            }
        }
    ]
}
```

### 1.2. Create Admin Bank Account

**Endpoint**: `POST /api/v1/admin/send-rekening`

**Purpose**: Create new bank account for admin

**Content-Type**: `multipart/form-data`

**Request Fields**:
- `kode_bank` (array, required): Array of bank codes
- `kode_bank.*` (string, required): Bank code (e.g., "014" for BCA)
- `nomor_rekening` (array, required): Array of account numbers
- `nomor_rekening.*` (string, required): Account number
- `nama_pemilik` (array, required): Array of account holder names
- `nama_pemilik.*` (string, required): Account holder name
- `photo_rek` (array, optional): Array of account photos
- `photo_rek.*` (file, optional): Account photo (jpeg, png, jpg, max: 2MB)

**Request Example**:
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "kode_bank[]=014" \
  -F "kode_bank[]=008" \
  -F "nomor_rekening[]=1234567890" \
  -F "nomor_rekening[]=0987654321" \
  -F "nama_pemilik[]=Admin User" \
  -F "nama_pemilik[]=Admin User 2" \
  -F "photo_rek[]=@account1.jpg" \
  -F "photo_rek[]=@account2.jpg" \
  /api/v1/admin/send-rekening
```

**Success Response (201 Created)**:
```json
{
    "data": [
        {
            "kode_bank": "014",
            "nomor_rekening": "1234567890",
            "nama_pemilik": "Admin User",
            "photo_rek": "http://localhost/storage/photos/photo-1693847200.jpg"
        },
        {
            "kode_bank": "008",
            "nomor_rekening": "0987654321",
            "nama_pemilik": "Admin User 2",
            "photo_rek": "http://localhost/storage/photos/photo-1693847300.jpg"
        }
    ],
    "message": "Rekenings have been successfully added!"
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "message": "User tidak boleh memiliki lebih dari 2 rekening."
}
```

### 1.3. Update Admin Bank Account

**Endpoint**: `PUT /api/v1/admin/update-rekening`

**Purpose**: Update existing bank account information

**Content-Type**: `multipart/form-data` (if uploading photo) or `application/json`

**Request Fields**:
- `rekenings` (array, required): Array of bank account updates
- `rekenings.*.id` (integer, required): Bank account ID
- `rekenings.*.kode_bank` (string|integer, required): Bank code
- `rekenings.*.nomor_rekening` (string, required): Account number
- `rekenings.*.nama_pemilik` (string, required): Account holder name
- `rekenings.*.photo_rek` (file, optional): New account photo

**Request Example (JSON)**:
```json
{
    "rekenings": [
        {
            "id": 1,
            "kode_bank": "014",
            "nomor_rekening": "1234567890",
            "nama_pemilik": "Updated Admin User"
        }
    ]
}
```

**Success Response (200 OK)**:
```json
{
    "data": {
        "id": 1,
        "kode_bank": "014",
        "nomor_rekening": "1234567890",
        "nama_pemilik": "Updated Admin User",
        "nama_bank": "Bank Central Asia",
        "photo_rek": "http://localhost/storage/photos/photo-1693847200.jpg"
    },
    "message": "Rekenings updated successfully!"
}
```

### 1.4. Delete Admin Bank Account

**Endpoint**: `DELETE /api/v1/admin/delete-rekening/{id}`

**Purpose**: Delete specific bank account

**Parameters**:
- `id` (integer, required): Bank account ID

**Request Example**:
```
DELETE /api/v1/admin/delete-rekening/1
```

**Success Response (200 OK)**:
```json
{
    "message": "Rekening deleted successfully."
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Rekening not found or does not belong to the user."
}
```

---

## 2. TRIPAY PAYMENT GATEWAY MANAGEMENT

### 2.1. Create Tripay Configuration

**Endpoint**: `POST /api/v1/admin/send-tripay`

**Purpose**: Configure Tripay payment gateway settings

**Request Fields**:
- `url_tripay` (string, required): Tripay API URL
- `private_key` (string, required): Tripay private key
- `api_key` (string, required): Tripay API key
- `kode_merchant` (string, required): Merchant code
- `methode_pembayaran` (string, required): Payment method name
- `id_methode_pembayaran` (string, required): Payment method ID

**Request Example**:
```json
{
    "url_tripay": "https://tripay.co.id/api/",
    "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
    "api_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
    "kode_merchant": "T1234",
    "methode_pembayaran": "Tripay",
    "id_methode_pembayaran": "2"
}
```

**Success Response (201 Created)**:
```json
{
    "message": "Setting Pembayaran Tripay berhasil disimpan",
    "data": {
        "id": 1,
        "user_id": 1,
        "url_tripay": "https://tripay.co.id/api/",
        "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
        "api_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
        "kode_merchant": "T1234",
        "methode_pembayaran": "Tripay",
        "id_methode_pembayaran": "2",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:00:00.000000Z"
    }
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "url_tripay": ["The url tripay field is required."],
        "private_key": ["The private key field is required."],
        "api_key": ["The api key field is required."],
        "kode_merchant": ["The kode merchant field is required."]
    }
}
```

**Error Response (500 Internal Server Error)**:
```json
{
    "message": "Setting Pembayaran Tripay tidak berhasil disimpan",
    "data": null
}
```

---

## 3. MIDTRANS PAYMENT GATEWAY MANAGEMENT

### 3.1. Create Midtrans Configuration

**Endpoint**: `POST /api/v1/admin/send-midtrans`

**Purpose**: Configure Midtrans payment gateway settings

**Request Fields**:
- `url` (string, required): Midtrans API URL
- `server_key` (string, required): Midtrans server key
- `client_key` (string, required): Midtrans client key
- `metode_production` (string, required): Production mode status
- `methode_pembayaran` (string, required): Payment method name
- `id_methode_pembayaran` (string, required): Payment method ID

**Request Example**:
```json
{
    "url": "https://api.sandbox.midtrans.com/v2/",
    "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
    "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
    "metode_production": "sandbox",
    "methode_pembayaran": "Midtrans",
    "id_methode_pembayaran": "3"
}
```

**Success Response (201 Created)**:
```json
{
    "message": "Setting Pembayaran Midtrans berhasil disimpan",
    "data": {
        "id": 1,
        "user_id": 1,
        "url": "https://api.sandbox.midtrans.com/v2/",
        "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
        "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
        "metode_production": "sandbox",
        "methode_pembayaran": "Midtrans",
        "id_methode_pembayaran": "3",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:00:00.000000Z"
    }
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "url": ["The url field is required."],
        "server_key": ["The server key field is required."],
        "client_key": ["The client key field is required."],
        "metode_production": ["The metode production field is required."]
    }
}
```

**Error Response (500 Internal Server Error)**:
```json
{
    "message": "Setting Pembayaran Midtrans tidak berhasil disimpan",
    "data": null
}
```

---

## 4. PAYMENT METHOD MANAGEMENT

### 4.1. Get Available Payment Methods

**Endpoint**: `GET /api/v1/all-tagihan`

**Purpose**: Retrieve all available payment methods for admin

**Request Example**:
```
GET /api/v1/all-tagihan
```

**Success Response (200 OK)**:
```json
{
    "data": [
        {
            "id": 1,
            "name": "Manual"
        },
        {
            "id": 2,
            "name": "Tripay"
        },
        {
            "id": 3,
            "name": "Midtrans"
        }
    ]
}
```

### 4.2. Create Payment Method Transaction

**Endpoint**: `POST /api/v1/admin/method-transaction`

**Purpose**: Create a new payment method transaction record

**Request Fields**:
- `metodeTransactions_id` (integer, required): Payment method ID (must exist in metode_transactions table)

**Request Example**:
```json
{
    "metodeTransactions_id": 2
}
```

**Success Response (201 Created)**:
```json
{
    "message": "Metode transaksi berhasil dibuat!",
    "data": {
        "id": 1,
        "user_id": 1,
        "metodeTransactions_id": 2,
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:00:00.000000Z"
    }
}
```

### 4.3. Get All Payment Methods Data

**Endpoint**: `GET /api/v1/list-methode-transaction/all`

**Purpose**: Retrieve unified data from all payment methods (Bank Accounts, Tripay, Midtrans) configured by admin users only

**Parameters**:
- `id_methode_pembayaran` (string, optional): Filter by payment method ID
- `methode_pembayaran` (string, optional): Filter by payment method name

**Note**: This endpoint automatically filters to show only payment methods created by admin users for security and data isolation.

**Request Example**:
```
GET /api/v1/list-methode-transaction/all?methode_pembayaran=Manual
```

**Success Response (200 OK)**:
```json
{
    "message": "Data metode transaksi admin berhasil diambil",
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "kode_bank": "014",
            "nomor_rekening": "1234567890",
            "nama_pemilik": "Admin User",
            "methode_pembayaran": "Manual",
            "id_methode_pembayaran": "1"
        },
        {
            "id": 1,
            "user_id": 1,
            "url_tripay": "https://tripay.co.id/api/",
            "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
            "methode_pembayaran": "Tripay",
            "id_methode_pembayaran": "2"
        },
        {
            "id": 1,
            "user_id": 1,
            "url": "https://api.sandbox.midtrans.com/v2/",
            "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
            "methode_pembayaran": "Midtrans",
            "id_methode_pembayaran": "3"
        }
    ]
}
```

---

## 5. PACKAGE MANAGEMENT

### 5.1. Get Wedding Packages

**Endpoint**: `GET /api/v1/admin/paket-undangan`

**Purpose**: Retrieve all wedding invitation packages

**Request Example**:
```
GET /api/v1/admin/paket-undangan
```

**Success Response (200 OK)**:
```json
{
    "message": "Data paket undangan yang tersedia saat ini.!",
    "data": [
        {
            "id": 1,
            "name_paket": "Basic Package",
            "price": 150000,
            "masa_aktif": 30,
            "halaman_buku": true,
            "kirim_wa": false,
            "bebas_pilih_tema": false,
            "kirim_hadiah": false,
            "import_data": false,
            "created_at": "2025-09-10T10:00:00.000000Z",
            "updated_at": "2025-09-10T10:00:00.000000Z"
        },
        {
            "id": 2,
            "name_paket": "Premium Package",
            "price": 250000,
            "masa_aktif": 60,
            "halaman_buku": true,
            "kirim_wa": true,
            "bebas_pilih_tema": true,
            "kirim_hadiah": true,
            "import_data": true,
            "created_at": "2025-09-10T10:00:00.000000Z",
            "updated_at": "2025-09-10T10:00:00.000000Z"
        }
    ]
}
```

### 5.2. Update Wedding Package

**Endpoint**: `PUT /api/v1/admin/paket-undangan/{id}`

**Purpose**: Update specific wedding package details

**Parameters**:
- `id` (integer, required): Package ID

**Request Fields**:
- `name_paket` (string, required): Package name
- `price` (numeric, required): Package price
- `masa_aktif` (integer, required): Active period in days
- `halaman_buku` (boolean, optional): Guest book page feature
- `kirim_wa` (boolean, optional): WhatsApp sending feature
- `bebas_pilih_tema` (boolean, optional): Free theme selection
- `kirim_hadiah` (boolean, optional): Gift sending feature
- `import_data` (boolean, optional): Data import feature

**Request Example**:
```json
{
    "name_paket": "Updated Premium Package",
    "price": 300000,
    "masa_aktif": 90,
    "halaman_buku": true,
    "kirim_wa": true,
    "bebas_pilih_tema": true,
    "kirim_hadiah": true,
    "import_data": true
}
```

**Success Response (200 OK)**:
```json
{
    "message": "Paket berhasil diperbarui",
    "data": {
        "id": 2,
        "name_paket": "Updated Premium Package",
        "price": 300000,
        "masa_aktif": 90,
        "halaman_buku": true,
        "kirim_wa": true,
        "bebas_pilih_tema": true,
        "kirim_hadiah": true,
        "import_data": true,
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:01:00.000000Z"
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Paket tidak ditemukan"
}
```

---

## 6. PUBLIC ENDPOINTS

### 6.1. Get Package Information (Public)

**Endpoint**: `GET /api/v1/list-paket-undangan`

**Purpose**: Public endpoint to get package information

**Parameters**:
- `id` (integer, optional): Specific package ID

**Request Example**:
```
GET /api/v1/list-paket-undangan?id=1
```

**Success Response (200 OK)**:
```json
{
    "message": "Data Paket Undangan berhasil diambil",
    "data": [
        {
            "id": 1,
            "name_paket": "Basic Package",
            "price": 150000,
            "masa_aktif": 30,
            "halaman_buku": true,
            "kirim_wa": false,
            "bebas_pilih_tema": false,
            "kirim_hadiah": false,
            "import_data": false
        }
    ]
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Paket Undangan tidak ditemukan",
    "data": []
}
```

---

## Data Structures

### Bank Account Object
```json
{
    "id": 1,
    "user_id": 1,
    "email": "admin@example.com",
    "methode_pembayaran": "Manual",
    "id_methode_pembayaran": "1",
    "nama_bank": "Bank Central Asia",
    "kode_bank": "014",
    "nomor_rekening": "1234567890",
    "nama_pemilik": "Admin User",
    "photo_rek": "http://localhost/storage/photos/rek-photo.jpg",
    "created_at": "2025-09-10T10:00:00.000000Z",
    "updated_at": "2025-09-10T10:00:00.000000Z"
}
```

### Tripay Configuration Object
```json
{
    "id": 1,
    "user_id": 1,
    "url_tripay": "https://tripay.co.id/api/",
    "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
    "api_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
    "kode_merchant": "T1234",
    "methode_pembayaran": "Tripay",
    "id_methode_pembayaran": "2",
    "created_at": "2025-09-10T10:00:00.000000Z",
    "updated_at": "2025-09-10T10:00:00.000000Z"
}
```

### Midtrans Configuration Object
```json
{
    "id": 1,
    "user_id": 1,
    "url": "https://api.sandbox.midtrans.com/v2/",
    "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
    "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
    "metode_production": "sandbox",
    "methode_pembayaran": "Midtrans",
    "id_methode_pembayaran": "3",
    "created_at": "2025-09-10T10:00:00.000000Z",
    "updated_at": "2025-09-10T10:00:00.000000Z"
}
```

### Payment Method Object
```json
{
    "id": 1,
    "name": "Manual"
}
```

### Package Object
```json
{
    "id": 1,
    "name_paket": "Basic Package",
    "price": 150000,
    "masa_aktif": 30,
    "halaman_buku": true,
    "kirim_wa": false,
    "bebas_pilih_tema": false,
    "kirim_hadiah": false,
    "import_data": false,
    "created_at": "2025-09-10T10:00:00.000000Z",
    "updated_at": "2025-09-10T10:00:00.000000Z"
}
```

---

## Error Handling

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

### Standard Error Response Format
```json
{
    "message": "Error description"
}
```

### Validation Error Response Format
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Error message 1", "Error message 2"]
    }
}
```

---

## Frontend Implementation Examples

### 1. Bank Account Management

#### Create Bank Account
```javascript
async function createBankAccount(formData) {
    try {
        const response = await fetch('/api/v1/admin/send-rekening', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
            },
            body: formData // FormData object with bank account data
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to create bank account');
        }
        
        return result;
    } catch (error) {
        console.error('Error creating bank account:', error);
        throw error;
    }
}

// Usage example
const bankAccountForm = document.getElementById('bankAccountForm');
bankAccountForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(bankAccountForm);
    
    try {
        const result = await createBankAccount(formData);
        alert(result.message);
        loadBankAccounts(); // Refresh list
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
```

#### Update Bank Account
```javascript
async function updateBankAccount(data) {
    try {
        const response = await fetch('/api/v1/admin/update-rekening', {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to update bank account');
        }
        
        return result;
    } catch (error) {
        console.error('Error updating bank account:', error);
        throw error;
    }
}
```

#### Delete Bank Account
```javascript
async function deleteBankAccount(id) {
    if (!confirm('Are you sure you want to delete this bank account?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/v1/admin/delete-rekening/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message);
        }
        
        alert(result.message);
        loadBankAccounts(); // Refresh list
        
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
```

### 2. Tripay Configuration

#### Setup Tripay
```javascript
async function setupTripay(tripayData) {
    try {
        const response = await fetch('/api/v1/admin/send-tripay', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(tripayData)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to setup Tripay');
        }
        
        return result;
    } catch (error) {
        console.error('Error setting up Tripay:', error);
        throw error;
    }
}

// Usage example
const tripayForm = {
    url_tripay: 'https://tripay.co.id/api/',
    private_key: 'DEV-xxxxxxxxxxxxxxxxxxxx',
    api_key: 'DEV-xxxxxxxxxxxxxxxxxxxx',
    kode_merchant: 'T1234',
    methode_pembayaran: 'Tripay',
    id_methode_pembayaran: '2'
};

setupTripay(tripayForm)
    .then(result => {
        console.log('Tripay setup successful:', result);
    })
    .catch(error => {
        console.error('Tripay setup failed:', error);
    });
```

### 3. Midtrans Configuration

#### Setup Midtrans
```javascript
async function setupMidtrans(midtransData) {
    try {
        const response = await fetch('/api/v1/admin/send-midtrans', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(midtransData)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to setup Midtrans');
        }
        
        return result;
    } catch (error) {
        console.error('Error setting up Midtrans:', error);
        throw error;
    }
}

// Usage example
const midtransForm = {
    url: 'https://api.sandbox.midtrans.com/v2/',
    server_key: 'SB-Mid-server-xxxxxxxxxxxxxxxxxxxx',
    client_key: 'SB-Mid-client-xxxxxxxxxxxxxxxxxxxx',
    metode_production: 'sandbox',
    methode_pembayaran: 'Midtrans',
    id_methode_pembayaran: '3'
};

setupMidtrans(midtransForm)
    .then(result => {
        console.log('Midtrans setup successful:', result);
    })
    .catch(error => {
        console.error('Midtrans setup failed:', error);
    });
```

### 4. Package Management

#### Update Package
```javascript
async function updatePackage(id, packageData) {
    try {
        const response = await fetch(`/api/v1/admin/paket-undangan/${id}`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(packageData)
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to update package');
        }
        
        return result;
    } catch (error) {
        console.error('Error updating package:', error);
        throw error;
    }
}
```

### 5. Unified Payment Methods Data

#### Get All Payment Methods
```javascript
async function getAllPaymentMethods(filters = {}) {
    try {
        const params = new URLSearchParams(filters);
        const url = `/api/v1/list-methode-transaction/all${params.toString() ? '?' + params.toString() : ''}`;
        
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to get payment methods');
        }
        
        return result;
    } catch (error) {
        console.error('Error getting payment methods:', error);
        throw error;
    }
}

// Usage example
getAllPaymentMethods({ methode_pembayaran: 'Manual' })
    .then(result => {
        console.log('Payment methods:', result.data);
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

---

## File Upload Guidelines

### Bank Account Photo Specifications
- **Accepted formats**: JPEG, PNG, JPG
- **Maximum size**: 2MB (2048 KB)
- **Storage path**: `storage/app/public/photos/`
- **Naming convention**: Auto-generated unique names
- **URL access**: `/storage/photos/{filename}`

### Frontend Photo Validation
```javascript
function validateAccountPhoto(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    // Check file type
    if (!allowedTypes.includes(file.type)) {
        throw new Error('Invalid file type. Please upload JPEG, PNG, or JPG.');
    }
    
    // Check file size
    if (file.size > maxSize) {
        throw new Error('File size too large. Maximum size is 2MB.');
    }
    
    return true;
}
```

---

## Security Considerations

### Input Validation
- All input fields are validated and sanitized
- File uploads restricted to images only
- Bank account number validation
- API key and private key encryption recommended

### Authorization & Access Control
- Admin role required for all operations
- Rate limiting: 100 requests per minute per user
- CSRF protection enabled for state-changing operations
- SQL injection protection via Eloquent ORM

### Sensitive Data Handling
- Payment gateway keys should be encrypted
- Bank account information properly secured
- Audit logs for payment configuration changes

---

## Testing Examples

### API Testing with cURL

#### Test Bank Account Creation
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "kode_bank[]=014" \
  -F "nomor_rekening[]=1234567890" \
  -F "nama_pemilik[]=Test Admin" \
  /api/v1/admin/send-rekening
```

#### Test Tripay Configuration
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "url_tripay": "https://tripay.co.id/api/",
    "private_key": "DEV-test-key",
    "api_key": "DEV-api-key",
    "kode_merchant": "T1234",
    "methode_pembayaran": "Tripay",
    "id_methode_pembayaran": "2"
  }' \
  /api/v1/admin/send-tripay
```

#### Test Midtrans Configuration
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://api.sandbox.midtrans.com/v2/",
    "server_key": "SB-Mid-server-test",
    "client_key": "SB-Mid-client-test",
    "metode_production": "sandbox",
    "methode_pembayaran": "Midtrans",
    "id_methode_pembayaran": "3"
  }' \
  /api/v1/admin/send-midtrans
```

### Frontend Testing Checklist
- [ ] Bank account creation with photo uploads
- [ ] Bank account listing and display
- [ ] Bank account update operations
- [ ] Bank account deletion with confirmation
- [ ] Tripay configuration setup
- [ ] Midtrans configuration setup
- [ ] Payment method listing
- [ ] Package management operations
- [ ] Unified payment data retrieval
- [ ] Error handling for all scenarios
- [ ] File upload validation
- [ ] Form validation and user feedback
- [ ] Authentication and authorization checks

---

## Additional Notes

### Payment Method IDs
Based on the seeder data:
- `1` - Manual (Bank Transfer)
- `2` - Tripay
- `3` - Midtrans
- `4` - Trial

---

## 7. MIDTRANS COMPLETE CRUD OPERATIONS

### 7.1. List All Midtrans Configurations

**Endpoint**: `GET /api/v1/admin/midtrans`

**Purpose**: Retrieve all Midtrans configurations for the authenticated admin

**Request Example**:
```
GET /api/v1/admin/midtrans
```

**Success Response (200 OK)**:
```json
{
    "message": "Data konfigurasi Midtrans berhasil diambil",
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "url": "https://api.sandbox.midtrans.com/v2/",
            "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
            "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
            "metode_production": "sandbox",
            "methode_pembayaran": "Midtrans",
            "id_methode_pembayaran": "3",
            "created_at": "2025-09-10T10:00:00.000000Z",
            "updated_at": "2025-09-10T10:00:00.000000Z"
        },
        {
            "id": 2,
            "user_id": 1,
            "url": "https://api.midtrans.com/v2/",
            "server_key": "Mid-server-production-xxxxxxxxxxxxxxxxxxxx",
            "client_key": "Mid-client-production-xxxxxxxxxxxxxxxxxxxx",
            "metode_production": "production",
            "methode_pembayaran": "Midtrans",
            "id_methode_pembayaran": "3",
            "created_at": "2025-09-10T11:00:00.000000Z",
            "updated_at": "2025-09-10T11:00:00.000000Z"
        }
    ]
}
```

### 7.2. Show Specific Midtrans Configuration

**Endpoint**: `GET /api/v1/admin/midtrans/{id}`

**Purpose**: Retrieve specific Midtrans configuration by ID

**Parameters**:
- `id` (integer, required): Midtrans configuration ID

**Request Example**:
```
GET /api/v1/admin/midtrans/1
```

**Success Response (200 OK)**:
```json
{
    "message": "Data konfigurasi Midtrans berhasil diambil",
    "data": {
        "id": 1,
        "user_id": 1,
        "url": "https://api.sandbox.midtrans.com/v2/",
        "server_key": "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
        "client_key": "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
        "metode_production": "sandbox",
        "methode_pembayaran": "Midtrans",
        "id_methode_pembayaran": "3",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:00:00.000000Z"
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses"
}
```

### 7.3. Update Midtrans Configuration

**Endpoint**: `PUT /api/v1/admin/midtrans/{id}`

**Purpose**: Update existing Midtrans configuration

**Parameters**:
- `id` (integer, required): Midtrans configuration ID

**Request Fields**:
- `url` (string, required): Midtrans API URL
- `server_key` (string, required): Midtrans server key
- `client_key` (string, required): Midtrans client key
- `metode_production` (string, required): Production mode status ("sandbox" or "production")
- `methode_pembayaran` (string, required): Payment method name
- `id_methode_pembayaran` (string, required): Payment method ID

**Request Example**:
```json
{
    "url": "https://api.midtrans.com/v2/",
    "server_key": "Mid-server-production-xxxxxxxxxxxxxxxxxxxx",
    "client_key": "Mid-client-production-xxxxxxxxxxxxxxxxxxxx",
    "metode_production": "production",
    "methode_pembayaran": "Midtrans",
    "id_methode_pembayaran": "3"
}
```

**Success Response (200 OK)**:
```json
{
    "message": "Konfigurasi Midtrans berhasil diperbarui",
    "data": {
        "id": 1,
        "user_id": 1,
        "url": "https://api.midtrans.com/v2/",
        "server_key": "Mid-server-production-xxxxxxxxxxxxxxxxxxxx",
        "client_key": "Mid-client-production-xxxxxxxxxxxxxxxxxxxx",
        "metode_production": "production",
        "methode_pembayaran": "Midtrans",
        "id_methode_pembayaran": "3",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T12:00:00.000000Z"
    }
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "message": "Data tidak valid",
    "errors": {
        "url": ["The url field is required."],
        "server_key": ["The server key field is required."],
        "client_key": ["The client key field is required."],
        "metode_production": ["The metode production field is required."]
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses"
}
```

### 7.4. Delete Midtrans Configuration

**Endpoint**: `DELETE /api/v1/admin/midtrans/{id}`

**Purpose**: Delete specific Midtrans configuration

**Parameters**:
- `id` (integer, required): Midtrans configuration ID

**Request Example**:
```
DELETE /api/v1/admin/midtrans/1
```

**Success Response (200 OK)**:
```json
{
    "message": "Konfigurasi Midtrans berhasil dihapus"
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Midtrans tidak ditemukan atau tidak memiliki akses"
}
```

---

## 8. TRIPAY COMPLETE CRUD OPERATIONS

### 8.1. List All Tripay Configurations

**Endpoint**: `GET /api/v1/admin/tripay`

**Purpose**: Retrieve all Tripay configurations for the authenticated admin

**Request Example**:
```
GET /api/v1/admin/tripay
```

**Success Response (200 OK)**:
```json
{
    "message": "Data konfigurasi Tripay berhasil diambil",
    "data": [
        {
            "id": 1,
            "user_id": 1,
            "url_tripay": "https://tripay.co.id/api/",
            "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
            "api_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
            "kode_merchant": "T1234",
            "methode_pembayaran": "Tripay",
            "id_methode_pembayaran": "2",
            "created_at": "2025-09-10T10:00:00.000000Z",
            "updated_at": "2025-09-10T10:00:00.000000Z"
        },
        {
            "id": 2,
            "user_id": 1,
            "url_tripay": "https://tripay.co.id/api-sandbox/",
            "private_key": "DEV-sandbox-xxxxxxxxxxxxxxxxxxxx",
            "api_key": "DEV-sandbox-xxxxxxxxxxxxxxxxxxxx",
            "kode_merchant": "T5678",
            "methode_pembayaran": "Tripay",
            "id_methode_pembayaran": "2",
            "created_at": "2025-09-10T11:00:00.000000Z",
            "updated_at": "2025-09-10T11:00:00.000000Z"
        }
    ]
}
```

### 8.2. Show Specific Tripay Configuration

**Endpoint**: `GET /api/v1/admin/tripay/{id}`

**Purpose**: Retrieve specific Tripay configuration by ID

**Parameters**:
- `id` (integer, required): Tripay configuration ID

**Request Example**:
```
GET /api/v1/admin/tripay/1
```

**Success Response (200 OK)**:
```json
{
    "message": "Data konfigurasi Tripay berhasil diambil",
    "data": {
        "id": 1,
        "user_id": 1,
        "url_tripay": "https://tripay.co.id/api/",
        "private_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
        "api_key": "DEV-xxxxxxxxxxxxxxxxxxxx",
        "kode_merchant": "T1234",
        "methode_pembayaran": "Tripay",
        "id_methode_pembayaran": "2",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T10:00:00.000000Z"
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses"
}
```

### 8.3. Update Tripay Configuration

**Endpoint**: `PUT /api/v1/admin/tripay/{id}`

**Purpose**: Update existing Tripay configuration

**Parameters**:
- `id` (integer, required): Tripay configuration ID

**Request Fields**:
- `url_tripay` (string, required): Tripay API URL
- `private_key` (string, required): Tripay private key
- `api_key` (string, required): Tripay API key
- `kode_merchant` (string, required): Merchant code
- `methode_pembayaran` (string, required): Payment method name
- `id_methode_pembayaran` (string, required): Payment method ID

**Request Example**:
```json
{
    "url_tripay": "https://tripay.co.id/api-sandbox/",
    "private_key": "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
    "api_key": "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
    "kode_merchant": "T9999",
    "methode_pembayaran": "Tripay",
    "id_methode_pembayaran": "2"
}
```

**Success Response (200 OK)**:
```json
{
    "message": "Konfigurasi Tripay berhasil diperbarui",
    "data": {
        "id": 1,
        "user_id": 1,
        "url_tripay": "https://tripay.co.id/api-sandbox/",
        "private_key": "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
        "api_key": "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
        "kode_merchant": "T9999",
        "methode_pembayaran": "Tripay",
        "id_methode_pembayaran": "2",
        "created_at": "2025-09-10T10:00:00.000000Z",
        "updated_at": "2025-09-10T12:00:00.000000Z"
    }
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "message": "Data tidak valid",
    "errors": {
        "url_tripay": ["The url tripay field is required."],
        "private_key": ["The private key field is required."],
        "api_key": ["The api key field is required."],
        "kode_merchant": ["The kode merchant field is required."]
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses"
}
```

### 8.4. Delete Tripay Configuration

**Endpoint**: `DELETE /api/v1/admin/tripay/{id}`

**Purpose**: Delete specific Tripay configuration

**Parameters**:
- `id` (integer, required): Tripay configuration ID

**Request Example**:
```
DELETE /api/v1/admin/tripay/1
```

**Success Response (200 OK)**:
```json
{
    "message": "Konfigurasi Tripay berhasil dihapus"
}
```

**Error Response (404 Not Found)**:
```json
{
    "message": "Konfigurasi Tripay tidak ditemukan atau tidak memiliki akses"
}
```

---

## 9. FRONTEND IMPLEMENTATION EXAMPLES

### 9.1. Midtrans Management Implementation

**JavaScript/TypeScript Implementation for Midtrans CRUD:**

```javascript
class MidtransService {
    constructor(baseURL, authToken) {
        this.baseURL = baseURL;
        this.authToken = authToken;
        this.headers = {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // Get all Midtrans configurations
    async getMidtransConfigs() {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/midtrans`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching Midtrans configs:', error);
            throw error;
        }
    }

    // Get specific Midtrans configuration
    async getMidtransConfig(id) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/midtrans/${id}`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching Midtrans config:', error);
            throw error;
        }
    }

    // Create new Midtrans configuration
    async createMidtransConfig(configData) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/send-midtrans`, {
                method: 'POST',
                headers: this.headers,
                body: JSON.stringify(configData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error creating Midtrans config:', error);
            throw error;
        }
    }

    // Update Midtrans configuration
    async updateMidtransConfig(id, configData) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/midtrans/${id}`, {
                method: 'PUT',
                headers: this.headers,
                body: JSON.stringify(configData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error updating Midtrans config:', error);
            throw error;
        }
    }

    // Delete Midtrans configuration
    async deleteMidtransConfig(id) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/midtrans/${id}`, {
                method: 'DELETE',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error deleting Midtrans config:', error);
            throw error;
        }
    }
}

// Usage Example
const midtransService = new MidtransService('http://localhost:8000/api', 'your-auth-token');

// Get all configurations
midtransService.getMidtransConfigs()
    .then(data => console.log('Midtrans configs:', data))
    .catch(error => console.error('Error:', error));

// Create new configuration
const newMidtransConfig = {
    url: "https://api.sandbox.midtrans.com/v2/",
    server_key: "SB-Mid-server-xxxxxxxxxxxxxxxxxxxx",
    client_key: "SB-Mid-client-xxxxxxxxxxxxxxxxxxxx",
    metode_production: "sandbox",
    methode_pembayaran: "Midtrans",
    id_methode_pembayaran: "3"
};

midtransService.createMidtransConfig(newMidtransConfig)
    .then(data => console.log('Created Midtrans config:', data))
    .catch(error => console.error('Error:', error));

// Update configuration
const updatedMidtransConfig = {
    url: "https://api.midtrans.com/v2/",
    server_key: "Mid-server-production-xxxxxxxxxxxxxxxxxxxx",
    client_key: "Mid-client-production-xxxxxxxxxxxxxxxxxxxx",
    metode_production: "production",
    methode_pembayaran: "Midtrans",
    id_methode_pembayaran: "3"
};

midtransService.updateMidtransConfig(1, updatedMidtransConfig)
    .then(data => console.log('Updated Midtrans config:', data))
    .catch(error => console.error('Error:', error));

// Delete configuration
midtransService.deleteMidtransConfig(1)
    .then(data => console.log('Deleted Midtrans config:', data))
    .catch(error => console.error('Error:', error));
```

### 9.2. Tripay Management Implementation

**JavaScript/TypeScript Implementation for Tripay CRUD:**

```javascript
class TripayService {
    constructor(baseURL, authToken) {
        this.baseURL = baseURL;
        this.authToken = authToken;
        this.headers = {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // Get all Tripay configurations
    async getTripayConfigs() {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/tripay`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching Tripay configs:', error);
            throw error;
        }
    }

    // Get specific Tripay configuration
    async getTripayConfig(id) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/tripay/${id}`, {
                method: 'GET',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching Tripay config:', error);
            throw error;
        }
    }

    // Create new Tripay configuration
    async createTripayConfig(configData) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/send-tripay`, {
                method: 'POST',
                headers: this.headers,
                body: JSON.stringify(configData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error creating Tripay config:', error);
            throw error;
        }
    }

    // Update Tripay configuration
    async updateTripayConfig(id, configData) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/tripay/${id}`, {
                method: 'PUT',
                headers: this.headers,
                body: JSON.stringify(configData)
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error updating Tripay config:', error);
            throw error;
        }
    }

    // Delete Tripay configuration
    async deleteTripayConfig(id) {
        try {
            const response = await fetch(`${this.baseURL}/v1/admin/tripay/${id}`, {
                method: 'DELETE',
                headers: this.headers
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error deleting Tripay config:', error);
            throw error;
        }
    }
}

// Usage Example
const tripayService = new TripayService('http://localhost:8000/api', 'your-auth-token');

// Get all configurations
tripayService.getTripayConfigs()
    .then(data => console.log('Tripay configs:', data))
    .catch(error => console.error('Error:', error));

// Create new configuration
const newTripayConfig = {
    url_tripay: "https://tripay.co.id/api/",
    private_key: "DEV-xxxxxxxxxxxxxxxxxxxx",
    api_key: "DEV-xxxxxxxxxxxxxxxxxxxx",
    kode_merchant: "T1234",
    methode_pembayaran: "Tripay",
    id_methode_pembayaran: "2"
};

tripayService.createTripayConfig(newTripayConfig)
    .then(data => console.log('Created Tripay config:', data))
    .catch(error => console.error('Error:', error));

// Update configuration
const updatedTripayConfig = {
    url_tripay: "https://tripay.co.id/api-sandbox/",
    private_key: "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
    api_key: "DEV-sandbox-newkey-xxxxxxxxxxxxxxxxxxxx",
    kode_merchant: "T9999",
    methode_pembayaran: "Tripay",
    id_methode_pembayaran: "2"
};

tripayService.updateTripayConfig(1, updatedTripayConfig)
    .then(data => console.log('Updated Tripay config:', data))
    .catch(error => console.error('Error:', error));

// Delete configuration
tripayService.deleteTripayConfig(1)
    .then(data => console.log('Deleted Tripay config:', data))
    .catch(error => console.error('Error:', error));
```

### 9.3. React/Vue Component Example

**React Component Example for Midtrans Management:**

```jsx
import React, { useState, useEffect } from 'react';

const MidtransManagement = () => {
    const [midtransConfigs, setMidtransConfigs] = useState([]);
    const [editingConfig, setEditingConfig] = useState(null);
    const [formData, setFormData] = useState({
        url: '',
        server_key: '',
        client_key: '',
        metode_production: 'sandbox',
        methode_pembayaran: 'Midtrans',
        id_methode_pembayaran: '3'
    });

    const midtransService = new MidtransService('http://localhost:8000/api', localStorage.getItem('authToken'));

    useEffect(() => {
        loadMidtransConfigs();
    }, []);

    const loadMidtransConfigs = async () => {
        try {
            const response = await midtransService.getMidtransConfigs();
            setMidtransConfigs(response.data);
        } catch (error) {
            console.error('Error loading Midtrans configs:', error);
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (editingConfig) {
                await midtransService.updateMidtransConfig(editingConfig.id, formData);
            } else {
                await midtransService.createMidtransConfig(formData);
            }
            
            loadMidtransConfigs();
            resetForm();
        } catch (error) {
            console.error('Error saving Midtrans config:', error);
        }
    };

    const handleEdit = (config) => {
        setEditingConfig(config);
        setFormData({
            url: config.url,
            server_key: config.server_key,
            client_key: config.client_key,
            metode_production: config.metode_production,
            methode_pembayaran: config.methode_pembayaran,
            id_methode_pembayaran: config.id_methode_pembayaran
        });
    };

    const handleDelete = async (id) => {
        if (window.confirm('Are you sure you want to delete this configuration?')) {
            try {
                await midtransService.deleteMidtransConfig(id);
                loadMidtransConfigs();
            } catch (error) {
                console.error('Error deleting Midtrans config:', error);
            }
        }
    };

    const resetForm = () => {
        setEditingConfig(null);
        setFormData({
            url: '',
            server_key: '',
            client_key: '',
            metode_production: 'sandbox',
            methode_pembayaran: 'Midtrans',
            id_methode_pembayaran: '3'
        });
    };

    return (
        <div className="midtrans-management">
            <h2>Midtrans Configuration Management</h2>
            
            {/* Form */}
            <form onSubmit={handleSubmit} className="config-form">
                <div>
                    <label>API URL:</label>
                    <input
                        type="url"
                        value={formData.url}
                        onChange={(e) => setFormData({...formData, url: e.target.value})}
                        placeholder="https://api.sandbox.midtrans.com/v2/"
                        required
                    />
                </div>
                
                <div>
                    <label>Server Key:</label>
                    <input
                        type="text"
                        value={formData.server_key}
                        onChange={(e) => setFormData({...formData, server_key: e.target.value})}
                        placeholder="SB-Mid-server-xxxxxxxxxxxxxxxxxxxx"
                        required
                    />
                </div>
                
                <div>
                    <label>Client Key:</label>
                    <input
                        type="text"
                        value={formData.client_key}
                        onChange={(e) => setFormData({...formData, client_key: e.target.value})}
                        placeholder="SB-Mid-client-xxxxxxxxxxxxxxxxxxxx"
                        required
                    />
                </div>
                
                <div>
                    <label>Production Mode:</label>
                    <select
                        value={formData.metode_production}
                        onChange={(e) => setFormData({...formData, metode_production: e.target.value})}
                        required
                    >
                        <option value="sandbox">Sandbox</option>
                        <option value="production">Production</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit">
                        {editingConfig ? 'Update Configuration' : 'Create Configuration'}
                    </button>
                    {editingConfig && (
                        <button type="button" onClick={resetForm}>Cancel</button>
                    )}
                </div>
            </form>
            
            {/* List */}
            <div className="config-list">
                <h3>Existing Configurations</h3>
                {midtransConfigs.map(config => (
                    <div key={config.id} className="config-item">
                        <div>
                            <strong>URL:</strong> {config.url}
                        </div>
                        <div>
                            <strong>Mode:</strong> {config.metode_production}
                        </div>
                        <div>
                            <strong>Created:</strong> {new Date(config.created_at).toLocaleDateString()}
                        </div>
                        <div className="actions">
                            <button onClick={() => handleEdit(config)}>Edit</button>
                            <button onClick={() => handleDelete(config.id)}>Delete</button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default MidtransManagement;
```

---

## 10. ERROR HANDLING AND BEST PRACTICES

### 10.1. Authentication Errors
All endpoints require admin authentication. Ensure proper token handling:

```javascript
// Handle authentication errors
const handleApiError = (error, response) => {
    if (response.status === 401) {
        // Redirect to login
        window.location.href = '/login';
    } else if (response.status === 403) {
        // Show unauthorized message
        alert('You do not have permission to perform this action');
    } else if (response.status === 404) {
        // Handle not found
        alert('Configuration not found');
    } else if (response.status === 422) {
        // Handle validation errors
        console.error('Validation errors:', error.errors);
    }
};
```

### 10.2. Security Considerations

1. **Token Management**: Store authentication tokens securely
2. **Input Validation**: Always validate user inputs on frontend before sending to API
3. **Error Messages**: Don't expose sensitive information in error messages
4. **HTTPS**: Always use HTTPS in production for API calls
5. **API Keys**: Never expose real API keys in frontend code

### 10.3. Data Validation

**Frontend Validation Example:**
```javascript
const validateMidtransConfig = (data) => {
    const errors = {};
    
    if (!data.url || !data.url.startsWith('https://')) {
        errors.url = 'Valid HTTPS URL is required';
    }
    
    if (!data.server_key || data.server_key.length < 10) {
        errors.server_key = 'Valid server key is required';
    }
    
    if (!data.client_key || data.client_key.length < 10) {
        errors.client_key = 'Valid client key is required';
    }
    
    if (!['sandbox', 'production'].includes(data.metode_production)) {
        errors.metode_production = 'Must be either sandbox or production';
    }
    
    return {
        isValid: Object.keys(errors).length === 0,
        errors
    };
};
```

---

### Bank Code Examples
Common Indonesian bank codes:
- `008` - Bank Mandiri
- `009` - Bank BNI
- `014` - Bank BCA
- `002` - Bank BRI

### Environment Configuration
Make sure to configure proper environment variables for:
- Tripay API credentials
- Midtrans API credentials
- File storage paths
- Database connections

This comprehensive API contract documentation provides all necessary information for frontend developers to implement complete payment management functionality with full CRUD operations for bank accounts, Tripay, and Midtrans payment gateways, including professional UPDATE and DELETE operations with proper security and validation.
