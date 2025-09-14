# Bank Account API Documentation

## Overview
Complete API documentation for bank account management endpoints in the Laravel application.

## Base Information
- **Base URL**: `http://127.0.0.1:8000`
- **Authentication**: Bearer Token (Sanctum)
- **Content-Type**: `application/json` (except file uploads)

---

## User Bank Account Endpoints

### 1. Get User Bank Accounts
**Endpoint**: `GET /api/v1/user/get-rekening`

**Headers**:
```
Authorization: Bearer {your_token}
Accept: application/json
```

**Response Success (200)**:
```json
{
    "data": [
        {
            "id": 11,
            "kode_bank": "003",
            "nomor_rekening": "43234234234",
            "nama_bank": "BANK EKSPOR INDONESIA",
            "nama_pemilik": "km",
            "photo_rek": null,
            "bank_info": {
                "id": 2,
                "name": "BANK EKSPOR INDONESIA",
                "kode_bank": "003"
            },
            "created_at": "2025-09-02T16:19:50.000000Z",
            "updated_at": "2025-09-02T16:19:50.000000Z"
        }
    ]
}
```

---

### 2. Create Bank Account
**Endpoint**: `POST /api/v1/user/send-rekening`

**Headers**:
```
Authorization: Bearer {your_token}
Accept: application/json
Content-Type: application/json
```

**Request Body**:
```json
{
    "kode_bank": ["003"],
    "nomor_rekening": ["43234234234"],
    "nama_pemilik": ["km"]
}
```

**Validation Rules**:
- `kode_bank.*`: Required, string, must exist in banks table
- `nomor_rekening.*`: Required, string
- `nama_pemilik.*`: Required, string
- `photo_rek.*`: Optional, file (jpeg,png,jpg), max 2MB

**Response Success (201)**:
```json
{
    "data": [
        {
            "kode_bank": "003",
            "nomor_rekening": "43234234234",
            "nama_pemilik": "km",
            "photo_rek": null
        }
    ],
    "message": "Rekenings have been successfully added!"
}
```

**Response Error (422)**:
```json
{
    "message": "User tidak boleh memiliki lebih dari 2 rekening."
}
```

---

### 3. Update Bank Account
**Endpoint**: `PUT /api/v1/user/update-rekening`

**Headers**:
```
Authorization: Bearer {your_token}
Accept: application/json
Content-Type: application/json
```

**Request Body**:
```json
{
    "rekenings": [
        {
            "id": 11,
            "kode_bank": "011",
            "nomor_rekening": "4323423423222",
            "nama_pemilik": "kmsdaaa"
        }
    ]
}
```

**Important Notes**:
- `kode_bank` can be sent as integer (11) or string ("011")
- System will auto-convert integer to proper 3-digit format
- `nama_bank` will be automatically updated when `kode_bank` changes
- Bank relationship will be properly loaded in response

**Validation Rules**:
- `rekenings.*.id`: Required, integer, must exist in rekenings table
- `rekenings.*.kode_bank`: Required, will be validated against banks table
- `rekenings.*.nomor_rekening`: Required, string
- `rekenings.*.nama_pemilik`: Required, string
- `rekenings.*.photo_rek`: Optional, file (jpeg,png,jpg), max 2MB

**Response Success (200)**:
```json
{
    "data": {
        "id": 11,
        "kode_bank": "011",
        "nomor_rekening": "4323423423222",
        "nama_bank": "BANK DANAMON",
        "nama_pemilik": "kmsdaaa",
        "photo_rek": null,
        "bank_info": {
            "id": 5,
            "name": "BANK DANAMON",
            "kode_bank": "011"
        },
        "created_at": "2025-09-02T16:19:50.000000Z",
        "updated_at": "2025-09-02T16:25:00.000000Z"
    },
    "message": "Rekenings updated successfully!"
}
```

**Response Error (422)**:
```json
{
    "message": "Bank dengan kode '99' tidak ditemukan. Kode bank yang valid: 002, 003, 008, 009, 011, 013, 014, 016, 019",
    "errors": {
        "rekenings.0.kode_bank": [
            "Bank code '99' does not exist."
        ]
    }
}
```

---

### 4. Delete Bank Account
**Endpoint**: `DELETE /api/v1/user/delete-rekening/{id}`

**Headers**:
```
Authorization: Bearer {your_token}
Accept: application/json
```

**Path Parameters**:
- `id`: Bank account ID to delete

**Response Success (200)**:
```json
{
    "message": "Rekening deleted successfully."
}
```

**Response Error (404)**:
```json
{
    "message": "Rekening not found or does not belong to the user."
}
```

---

## Available Bank Codes

### Get All Banks
**Endpoint**: `GET /api/v1/all-bank`

**Response**:
```json
{
    "data": [
        {
            "id": 1,
            "kode_bank": "002",
            "name": "BANK BRI",
            "logo": "bri.png"
        },
        {
            "id": 2,
            "kode_bank": "003",
            "name": "BANK EKSPOR INDONESIA",
            "logo": "bei.png"
        },
        {
            "id": 3,
            "kode_bank": "008",
            "name": "BANK MANDIRI",
            "logo": "mandiri.png"
        },
        {
            "id": 4,
            "kode_bank": "009",
            "name": "BANK BNI",
            "logo": "bni.png"
        },
        {
            "id": 5,
            "kode_bank": "011",
            "name": "BANK DANAMON",
            "logo": "danamon.png"
        }
    ]
}
```

---

## Business Rules

### Account Limits
- **Maximum 2 bank accounts per user**
- Enforced during creation and update operations

### Bank Code Format
- **Storage Format**: 3-digit zero-padded strings ("002", "011")
- **Input Flexibility**: Accepts both integer (11) and string ("011")
- **Auto-conversion**: Integer inputs are converted to proper format
- **Validation**: Must exist in banks table

### Data Consistency
- `nama_bank` automatically updates when `kode_bank` changes
- Bank relationships are properly loaded in responses
- Photo uploads are handled for account logos

---

## Error Handling

### Validation Errors (422)
```json
{
    "message": "Validation failed. Please check the form inputs.",
    "errors": {
        "rekenings.0.kode_bank": [
            "Bank code '99' does not exist."
        ]
    }
}
```

### Authentication Errors (401)
```json
{
    "message": "Unauthenticated."
}
```

### Not Found Errors (404)
```json
{
    "message": "Rekening not found or does not belong to the user."
}
```

### Server Errors (500)
```json
{
    "message": "An error occurred while updating the records.",
    "error": "Detailed error message"
}
```

---

## Testing Examples

### Example 1: Create Account
```bash
curl -X POST http://127.0.0.1:8000/api/v1/user/send-rekening \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kode_bank": ["003"],
    "nomor_rekening": ["1234567890"],
    "nama_pemilik": ["John Doe"]
  }'
```

### Example 2: Update Account
```bash
curl -X PUT http://127.0.0.1:8000/api/v1/user/update-rekening \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "rekenings": [{
      "id": 11,
      "kode_bank": 11,
      "nomor_rekening": "9876543210",
      "nama_pemilik": "Jane Doe"
    }]
  }'
```

### Example 3: Get Accounts
```bash
curl -X GET http://127.0.0.1:8000/api/v1/user/get-rekening \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## Migration from Old API

If you're using the old format where `bank_info` becomes null after updates:

### Before (Broken)
```json
{
    "rekenings": [{
        "id": 11,
        "kode_bank": 11,  // This breaks relationships
        "nomor_rekening": "123",
        "nama_pemilik": "Test"
    }]
}
```

### After (Fixed)
The same payload now works correctly:
- Integer `11` is converted to `"011"`
- `nama_bank` updates to "BANK DANAMON"
- `bank_info` is properly loaded with bank details
- Relationships work correctly

---

## Security Considerations

1. **Authentication Required**: All endpoints require valid Bearer token
2. **User Isolation**: Users can only access their own accounts
3. **Input Validation**: All inputs are validated and sanitized
4. **File Upload Security**: Photo uploads are validated for type and size
5. **SQL Injection Prevention**: Uses Eloquent ORM and parameter binding

---

*Last Updated: September 2, 2025*