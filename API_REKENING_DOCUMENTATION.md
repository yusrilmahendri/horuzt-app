# Rekening (Bank Account) API Documentation

## Overview
API untuk mengelola rekening bank pengguna. Setiap user dapat memiliki maksimal 2 rekening.

## Authentication
Semua endpoint memerlukan authentication Bearer token (Sanctum).

---

## USER ENDPOINTS

### 1. Create Rekening (User)
**Endpoint:** `POST /api/v1/user/send-rekening`

**Description:** Menambahkan rekening baru untuk user yang sedang login.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Payload (FormData):**
```
kode_bank: string (required) - Kode bank (contoh: "002", "014")
nomor_rekening: string (required) - Nomor rekening
nama_pemilik: string (required) - Nama pemilik rekening
photo_rek: file (optional) - Foto rekening (jpeg, png, jpg, max 2MB)
```

**Response Success (201):**
```json
{
    "data": {
        "id": 1,
        "kode_bank": "002",
        "nomor_rekening": "1234567890",
        "nama_bank": "Bank BRI",
        "nama_pemilik": "John Doe",
        "photo_rek": "http://localhost/storage/photos/xxx.jpg",
        "bank_info": {
            "id": 2,
            "name": "Bank BRI",
            "kode_bank": "002"
        },
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "message": "Rekening berhasil ditambahkan!"
}
```

**Response Error (422) - Validation:**
```json
{
    "errors": {
        "kode_bank": ["Bank code '999' does not exist."],
        "nomor_rekening": ["The nomor rekening field is required."]
    },
    "message": "Validation failed!"
}
```

**Response Error (422) - Limit Exceeded:**
```json
{
    "message": "User tidak boleh memiliki lebih dari 2 rekening."
}
```

---

### 2. Update Rekening (User)
**Endpoint:** `PUT /api/v1/user/update-rekening/{id}`

**Description:** Mengupdate rekening berdasarkan ID untuk user yang sedang login.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Payload (FormData):**
```
kode_bank: string (required) - Kode bank (contoh: "002", "014")
nomor_rekening: string (required) - Nomor rekening
nama_pemilik: string (required) - Nama pemilik rekening
photo_rek: file (optional) - Foto rekening (jpeg, png, jpg, max 2MB)
```

**Response Success (200):**
```json
{
    "data": {
        "id": 1,
        "kode_bank": "014",
        "nomor_rekening": "9876543210",
        "nama_bank": "Bank BCA",
        "nama_pemilik": "John Doe",
        "photo_rek": "http://localhost/storage/photos/xxx.jpg",
        "bank_info": {
            "id": 3,
            "name": "Bank BCA",
            "kode_bank": "014"
        },
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T01:00:00.000000Z"
    },
    "message": "Rekening berhasil diperbarui!"
}
```

**Response Error (404):**
```json
{
    "message": "Rekening not found or does not belong to the user."
}
```

---

### 3. Get All Rekening (User)
**Endpoint:** `GET /api/v1/user/get-rekening`

**Description:** Mengambil semua rekening milik user yang sedang login.

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
    "data": [
        {
            "id": 1,
            "kode_bank": "002",
            "nomor_rekening": "1234567890",
            "nama_bank": "Bank BRI",
            "nama_pemilik": "John Doe",
            "photo_rek": "http://localhost/storage/photos/xxx.jpg",
            "bank_info": {
                "id": 2,
                "name": "Bank BRI",
                "kode_bank": "002"
            },
            "created_at": "2024-01-01T00:00:00.000000Z",
            "updated_at": "2024-01-01T00:00:00.000000Z"
        }
    ]
}
```

---

### 4. Delete Rekening (User)
**Endpoint:** `DELETE /api/v1/user/delete-rekening/{id}`

**Description:** Menghapus rekening berdasarkan ID untuk user yang sedang login.

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
    "message": "Rekening deleted successfully."
}
```

**Response Error (404):**
```json
{
    "message": "Rekening not found or does not belong to the user."
}
```

---

## ADMIN ENDPOINTS

### 1. Create Rekening (Admin)
**Endpoint:** `POST /api/v1/admin/send-rekening`

**Description:** Admin dapat menambahkan rekening untuk user tertentu.

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data
```

**Payload (FormData):**
```
user_id: integer (optional) - ID user target (jika tidak diisi, akan menggunakan ID admin)
kode_bank: string (required) - Kode bank (contoh: "002", "014")
nomor_rekening: string (required) - Nomor rekening
nama_pemilik: string (required) - Nama pemilik rekening
photo_rek: file (optional) - Foto rekening (jpeg, png, jpg, max 2MB)
```

**Response Success (201):**
```json
{
    "data": {
        "id": 1,
        "kode_bank": "002",
        "nomor_rekening": "1234567890",
        "nama_bank": "Bank BRI",
        "nama_pemilik": "John Doe",
        "photo_rek": "http://localhost/storage/photos/xxx.jpg",
        "bank_info": {
            "id": 2,
            "name": "Bank BRI",
            "kode_bank": "002"
        },
        "created_at": "2024-01-01T00:00:00.000000Z",
        "updated_at": "2024-01-01T00:00:00.000000Z"
    },
    "message": "Rekening berhasil ditambahkan!"
}
```

---

### 2. Update Rekening (Admin)
**Endpoint:** `PUT /api/v1/admin/update-rekening/{id}`

**Description:** Admin dapat mengupdate rekening berdasarkan ID.

**Headers:**
```
Authorization: Bearer {admin_token}
Content-Type: multipart/form-data
```

**Payload (FormData):**
```
kode_bank: string (required) - Kode bank (contoh: "002", "014")
nomor_rekening: string (required) - Nomor rekening
nama_pemilik: string (required) - Nama pemilik rekening
photo_rek: file (optional) - Foto rekening (jpeg, png, jpg, max 2MB)
```

**Response:** Same as user update endpoint

---

### 3. Get All Rekening (Admin)
**Endpoint:** `GET /api/v1/admin/get-rekening`

**Description:** Admin dapat melihat semua rekening dari semua user.

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Response:** Similar to user endpoint but includes all users' accounts

---

### 4. Delete Rekening (Admin)
**Endpoint:** `DELETE /api/v1/admin/delete-rekening/{id}`

**Description:** Admin dapat menghapus rekening berdasarkan ID.

**Headers:**
```
Authorization: Bearer {admin_token}
```

**Response:** Same as user delete endpoint

---

## Error Responses

### General Error (500):
```json
{
    "message": "Terjadi kesalahan saat memproses request.",
    "error": "Error details"
}
```

### Validation Error (422):
```json
{
    "message": "Validation failed!",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

### Authentication Error (401):
```json
{
    "message": "Unauthenticated."
}
```

---

## Important Notes

1. **File Upload**: Gunakan FormData untuk upload file. Field `photo_rek` adalah optional.

2. **Bank Code**: Kode bank akan dinormalisasi otomatis (misal: input "2" akan menjadi "002").

3. **User Limit**: Setiap user maksimal memiliki 2 rekening.

4. **Admin Privileges**: Admin dapat menggunakan `user_id` parameter untuk menambah rekening ke user lain.

5. **Security**: Update dan delete hanya bisa dilakukan pada rekening milik sendiri (kecuali admin).

## Frontend Implementation Example

### JavaScript (FormData):
```javascript
// Create rekening
const formData = new FormData();
formData.append('kode_bank', '002');
formData.append('nomor_rekening', '1234567890');
formData.append('nama_pemilik', 'John Doe');
formData.append('photo_rek', fileInput.files[0]); // optional

fetch('/api/v1/user/send-rekening', {
    method: 'POST',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: formData
})
.then(response => response.json())
.then(data => console.log(data));

// Update rekening
const updateFormData = new FormData();
updateFormData.append('kode_bank', '014');
updateFormData.append('nomor_rekening', '9876543210');
updateFormData.append('nama_pemilik', 'John Doe Updated');

fetch('/api/v1/user/update-rekening/1', {
    method: 'PUT',
    headers: {
        'Authorization': `Bearer ${token}`
    },
    body: updateFormData
})
.then(response => response.json())
.then(data => console.log(data));
```
