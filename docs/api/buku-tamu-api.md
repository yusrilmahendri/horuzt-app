# Buku Tamu API Documentation

Digital Wedding Guest Book API for managing guest entries, attendance confirmations, and wishes.

Version: 1.0.0
Base URL: /api/v1


## Public Endpoints

These endpoints do not require authentication. Used by wedding invitation guests.


### 1. GET Buku Tamu List (Public)

Endpoint: /api/v1/buku-tamu
Method: GET
Description: Retrieve list of approved guest book entries for a specific wedding invitation.

Query Parameters:

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| user_id   | int    | Yes*     | Wedding owner user ID                    |
| domain    | string | Yes*     | Wedding domain/slug (alternative)        |
| limit     | int    | No       | Items per page. Default: 50              |
| status    | string | No       | Filter by: hadir, tidak_hadir, ragu      |

*Either user_id or domain is required.

Headers:

    Content-Type: application/json

Response Example:

    {
      "status": 200,
      "message": "string",
      "data": [
        {
          "id": "uint64",
          "user_id": "uint64",
          "nama": "string",
          "email": "string|null",
          "telepon": "string|null",
          "ucapan": "string|null",
          "status_kehadiran": "enum (hadir|tidak_hadir|ragu)",
          "status_kehadiran_label": "string (Hadir|Tidak Hadir|Masih Ragu)",
          "jumlah_tamu": "int",
          "is_approved": "boolean",
          "created_at": "datetime ISO8601",
          "updated_at": "datetime ISO8601",
          "created_at_human": "string (e.g., '2 jam yang lalu')"
        }
      ],
      "pagination": {
        "total": "int",
        "per_page": "int",
        "current_page": "int",
        "last_page": "int"
      }
    }

Error Response (400):

    {
      "status": 400,
      "message": "Parameter user_id atau domain wajib diisi."
    }


### 2. POST Buku Tamu Entry (Public)

Endpoint: /api/v1/buku-tamu
Method: POST
Description: Submit a new guest book entry with attendance confirmation and wishes.

Headers:

    Content-Type: application/json

Request Body:

    {
      "user_id": "uint64 (required)",
      "nama": "string (required, min:2, max:100)",
      "email": "string|null (email format, max:100)",
      "telepon": "string|null (max:20)",
      "ucapan": "string|null (min:5, max:1000)",
      "status_kehadiran": "enum (required: hadir|tidak_hadir|ragu)",
      "jumlah_tamu": "int|null (min:1, max:20, default:1)"
    }

Request Example:

    {
      "user_id": 123,
      "nama": "Budi Santoso",
      "email": "budi@example.com",
      "telepon": "08123456789",
      "ucapan": "Selamat menempuh hidup baru! Semoga menjadi keluarga sakinah mawaddah warahmah.",
      "status_kehadiran": "hadir",
      "jumlah_tamu": 2
    }

Response Example (201):

    {
      "status": 201,
      "message": "Ucapan dan konfirmasi kehadiran berhasil disimpan.",
      "data": {
        "id": "uint64",
        "user_id": "uint64",
        "nama": "string",
        "email": "string|null",
        "telepon": "string|null",
        "ucapan": "string|null",
        "status_kehadiran": "string",
        "status_kehadiran_label": "string",
        "jumlah_tamu": "int",
        "is_approved": "boolean",
        "created_at": "datetime ISO8601",
        "updated_at": "datetime ISO8601",
        "created_at_human": "string"
      }
    }

Validation Error Response (422):

    {
      "status": 422,
      "message": "Validation failed",
      "errors": {
        "nama": ["Nama wajib diisi."],
        "status_kehadiran": ["Status kehadiran wajib dipilih."]
      }
    }


### 3. GET Buku Tamu Statistics (Public)

Endpoint: /api/v1/buku-tamu/statistics
Method: GET
Description: Retrieve attendance statistics for a wedding invitation.

Query Parameters:

| Parameter | Type   | Required | Description                        |
|-----------|--------|----------|------------------------------------|
| user_id   | int    | Yes*     | Wedding owner user ID              |
| domain    | string | Yes*     | Wedding domain/slug (alternative)  |

*Either user_id or domain is required.

Headers:

    Content-Type: application/json

Response Example:

    {
      "status": 200,
      "message": "Statistik buku tamu berhasil diambil.",
      "data": {
        "total_entries": "int",
        "total_hadir": "int",
        "total_tidak_hadir": "int",
        "total_ragu": "int",
        "total_tamu_hadir": "int",
        "percentage_hadir": "float",
        "percentage_tidak_hadir": "float",
        "percentage_ragu": "float"
      }
    }


## User Endpoints

These endpoints require authentication with Sanctum token and user role.


### 4. GET Buku Tamu List (User)

Endpoint: /api/v1/user/result-bukutamu
Method: GET
Description: Retrieve all guest book entries for authenticated user's wedding invitation.
Authentication: Required (Bearer Token)

Query Parameters:

| Parameter  | Type   | Required | Description                         |
|------------|--------|----------|-------------------------------------|
| limit      | int    | No       | Items per page. Default: 15         |
| search     | string | No       | Search by nama, email, or ucapan    |
| status     | string | No       | Filter by: hadir, tidak_hadir, ragu |
| sort_by    | string | No       | Sort field: created_at, nama, status_kehadiran |
| sort_order | string | No       | Sort direction: asc, desc           |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "data": [
        {
          "id": "uint64",
          "user_id": "uint64",
          "nama": "string",
          "email": "string|null",
          "telepon": "string|null",
          "ucapan": "string|null",
          "status_kehadiran": "string",
          "status_kehadiran_label": "string",
          "jumlah_tamu": "int",
          "is_approved": "boolean",
          "created_at": "datetime ISO8601",
          "updated_at": "datetime ISO8601",
          "created_at_human": "string"
        }
      ],
      "pagination": {
        "total": "int",
        "per_page": "int",
        "current_page": "int",
        "last_page": "int",
        "from": "int|null",
        "to": "int|null"
      },
      "statistics": {
        "total_entries": "int",
        "total_hadir": "int",
        "total_tidak_hadir": "int",
        "total_ragu": "int",
        "total_tamu_hadir": "int",
        "today_entries": "int",
        "approved_entries": "int",
        "pending_entries": "int",
        "percentage_hadir": "float",
        "percentage_tidak_hadir": "float",
        "percentage_ragu": "float"
      }
    }


### 5. GET Buku Tamu Detail (User)

Endpoint: /api/v1/user/buku-tamu/{id}
Method: GET
Description: Retrieve single guest book entry detail.
Authentication: Required (Bearer Token)

Path Parameters:

| Parameter | Type | Required | Description       |
|-----------|------|----------|-------------------|
| id        | int  | Yes      | Buku tamu ID      |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example (200):

    {
      "status": 200,
      "message": "Detail buku tamu berhasil diambil.",
      "data": {
        "id": "uint64",
        "user_id": "uint64",
        "nama": "string",
        "email": "string|null",
        "telepon": "string|null",
        "ucapan": "string|null",
        "status_kehadiran": "string",
        "status_kehadiran_label": "string",
        "jumlah_tamu": "int",
        "is_approved": "boolean",
        "created_at": "datetime ISO8601",
        "updated_at": "datetime ISO8601",
        "created_at_human": "string"
      }
    }

Error Response (404):

    {
      "status": 404,
      "message": "Data buku tamu tidak ditemukan."
    }


### 6. GET Buku Tamu Statistics (User)

Endpoint: /api/v1/user/buku-tamu/statistics
Method: GET
Description: Retrieve attendance statistics for authenticated user.
Authentication: Required (Bearer Token)

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Statistik buku tamu berhasil diambil.",
      "data": {
        "total_entries": "int",
        "total_hadir": "int",
        "total_tidak_hadir": "int",
        "total_ragu": "int",
        "total_tamu_hadir": "int",
        "today_entries": "int",
        "approved_entries": "int",
        "pending_entries": "int",
        "percentage_hadir": "float",
        "percentage_tidak_hadir": "float",
        "percentage_ragu": "float"
      }
    }


### 7. PATCH Update Approval Status (User)

Endpoint: /api/v1/user/buku-tamu/{id}/approval
Method: PATCH
Description: Update approval status for a single guest book entry.
Authentication: Required (Bearer Token)

Path Parameters:

| Parameter | Type | Required | Description  |
|-----------|------|----------|--------------|
| id        | int  | Yes      | Buku tamu ID |

Request Body:

    {
      "is_approved": "boolean (required)"
    }

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Ucapan berhasil disetujui.",
      "data": {
        "id": "uint64",
        "is_approved": "boolean",
        ...
      }
    }


### 8. PATCH Bulk Update Approval (User)

Endpoint: /api/v1/user/buku-tamu/bulk-approval
Method: PATCH
Description: Update approval status for multiple guest book entries.
Authentication: Required (Bearer Token)

Request Body:

    {
      "ids": "array of uint64 (required, min:1)",
      "is_approved": "boolean (required)"
    }

Request Example:

    {
      "ids": [1, 2, 3, 4, 5],
      "is_approved": true
    }

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "5 data berhasil diperbarui.",
      "data": {
        "updated_count": 5
      }
    }


### 9. GET Export Buku Tamu (User)

Endpoint: /api/v1/user/buku-tamu/export
Method: GET
Description: Export all guest book entries in JSON or CSV format.
Authentication: Required (Bearer Token)

Query Parameters:

| Parameter | Type   | Required | Description                   |
|-----------|--------|----------|-------------------------------|
| format    | string | No       | Export format: json, csv      |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example (CSV):

    {
      "status": 200,
      "message": "Data buku tamu berhasil diekspor.",
      "data": {
        "content": "base64 encoded CSV content",
        "filename": "buku_tamu_2026-01-26.csv",
        "mime_type": "text/csv"
      }
    }


### 10. DELETE Single Entry (User)

Endpoint: /api/v1/user/buku-tamu/{id}
Method: DELETE
Description: Delete a single guest book entry.
Authentication: Required (Bearer Token)

Path Parameters:

| Parameter | Type | Required | Description  |
|-----------|------|----------|--------------|
| id        | int  | Yes      | Buku tamu ID |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Data buku tamu berhasil dihapus."
    }


### 11. DELETE All Entries (User)

Endpoint: /api/v1/user/buku-tamu/delete-all
Method: DELETE
Description: Delete all guest book entries for authenticated user.
Authentication: Required (Bearer Token)

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Semua data buku tamu berhasil dihapus (125 data).",
      "data": {
        "deleted_count": 125
      }
    }


## Admin Endpoints

These endpoints require authentication with Sanctum token and admin role.


### 12. GET All Buku Tamu (Admin)

Endpoint: /api/v1/admin/buku-tamu
Method: GET
Description: Retrieve all guest book entries across all users.
Authentication: Required (Bearer Token, Admin Role)

Query Parameters:

| Parameter   | Type   | Required | Description                              |
|-------------|--------|----------|------------------------------------------|
| limit       | int    | No       | Items per page. Default: 15              |
| user_id     | int    | No       | Filter by specific user                  |
| status      | string | No       | Filter by: hadir, tidak_hadir, ragu      |
| is_approved | bool   | No       | Filter by approval status                |
| search      | string | No       | Search by nama, email, ucapan            |
| sort_by     | string | No       | Sort: created_at, nama, status_kehadiran, user_id |
| sort_order  | string | No       | Sort direction: asc, desc                |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Data buku tamu berhasil diambil.",
      "data": [
        {
          "id": "uint64",
          "user_id": "uint64",
          "nama": "string",
          "email": "string|null",
          "telepon": "string|null",
          "ucapan": "string|null",
          "status_kehadiran": "string",
          "status_kehadiran_label": "string",
          "jumlah_tamu": "int",
          "is_approved": "boolean",
          "ip_address": "string|null",
          "created_at": "datetime ISO8601",
          "updated_at": "datetime ISO8601",
          "created_at_human": "string"
        }
      ],
      "pagination": {
        "total": "int",
        "per_page": "int",
        "current_page": "int",
        "last_page": "int",
        "from": "int|null",
        "to": "int|null"
      }
    }


### 13. GET Buku Tamu Detail (Admin)

Endpoint: /api/v1/admin/buku-tamu/{id}
Method: GET
Description: Retrieve single guest book entry detail with IP address.
Authentication: Required (Bearer Token, Admin Role)

Path Parameters:

| Parameter | Type | Required | Description  |
|-----------|------|----------|--------------|
| id        | int  | Yes      | Buku tamu ID |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Detail buku tamu berhasil diambil.",
      "data": {
        "id": "uint64",
        "user_id": "uint64",
        "nama": "string",
        "email": "string|null",
        "telepon": "string|null",
        "ucapan": "string|null",
        "status_kehadiran": "string",
        "status_kehadiran_label": "string",
        "jumlah_tamu": "int",
        "is_approved": "boolean",
        "ip_address": "string|null",
        "created_at": "datetime ISO8601",
        "updated_at": "datetime ISO8601",
        "created_at_human": "string"
      }
    }


### 14. GET Statistics (Admin)

Endpoint: /api/v1/admin/buku-tamu/statistics
Method: GET
Description: Retrieve global or per-user guest book statistics.
Authentication: Required (Bearer Token, Admin Role)

Query Parameters:

| Parameter | Type | Required | Description                    |
|-----------|------|----------|--------------------------------|
| user_id   | int  | No       | Filter statistics by user ID   |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Statistik buku tamu berhasil diambil.",
      "data": {
        "total_entries": "int",
        "total_hadir": "int",
        "total_tidak_hadir": "int",
        "total_ragu": "int",
        "total_tamu_hadir": "int",
        "today_entries": "int",
        "approved_entries": "int",
        "pending_entries": "int",
        "percentage_hadir": "float",
        "percentage_tidak_hadir": "float",
        "percentage_ragu": "float",
        "total_users_with_entries": "int",
        "entries_per_user": [
          {
            "user_id": "uint64",
            "total": "int"
          }
        ]
      }
    }


### 15. PATCH Update Approval (Admin)

Endpoint: /api/v1/admin/buku-tamu/{id}/approval
Method: PATCH
Description: Update approval status for any guest book entry.
Authentication: Required (Bearer Token, Admin Role)

Path Parameters:

| Parameter | Type | Required | Description  |
|-----------|------|----------|--------------|
| id        | int  | Yes      | Buku tamu ID |

Request Body:

    {
      "is_approved": "boolean (required)"
    }

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Ucapan berhasil disetujui.",
      "data": {
        "id": "uint64",
        "is_approved": "boolean",
        ...
      }
    }


### 16. PATCH Bulk Update Approval (Admin)

Endpoint: /api/v1/admin/buku-tamu/bulk-approval
Method: PATCH
Description: Update approval status for multiple entries.
Authentication: Required (Bearer Token, Admin Role)

Request Body:

    {
      "ids": "array of uint64 (required, min:1)",
      "is_approved": "boolean (required)"
    }

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "10 data berhasil diperbarui.",
      "data": {
        "updated_count": 10
      }
    }


### 17. DELETE Single Entry (Admin)

Endpoint: /api/v1/admin/buku-tamu/{id}
Method: DELETE
Description: Delete any guest book entry.
Authentication: Required (Bearer Token, Admin Role)

Path Parameters:

| Parameter | Type | Required | Description  |
|-----------|------|----------|--------------|
| id        | int  | Yes      | Buku tamu ID |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Data buku tamu berhasil dihapus."
    }


### 18. DELETE Bulk Delete (Admin)

Endpoint: /api/v1/admin/buku-tamu/bulk-delete
Method: DELETE
Description: Delete multiple guest book entries at once.
Authentication: Required (Bearer Token, Admin Role)

Request Body:

    {
      "ids": "array of uint64 (required, min:1)"
    }

Request Example:

    {
      "ids": [1, 2, 3, 4, 5]
    }

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "5 data berhasil dihapus.",
      "data": {
        "deleted_count": 5
      }
    }


### 19. DELETE By User (Admin)

Endpoint: /api/v1/admin/buku-tamu/user/{userId}
Method: DELETE
Description: Delete all guest book entries for a specific user.
Authentication: Required (Bearer Token, Admin Role)

Path Parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| userId    | int  | Yes      | User ID     |

Headers:

    Content-Type: application/json
    Authorization: Bearer {token}

Response Example:

    {
      "status": 200,
      "message": "Semua buku tamu untuk user 123 berhasil dihapus (45 data).",
      "data": {
        "deleted_count": 45
      }
    }


## Error Codes

| Code | Description                    |
|------|--------------------------------|
| 200  | Success                        |
| 201  | Created                        |
| 400  | Bad Request                    |
| 401  | Unauthorized                   |
| 403  | Forbidden                      |
| 404  | Not Found                      |
| 422  | Validation Error               |
| 500  | Internal Server Error          |


## Status Kehadiran Values

| Value        | Label          | Description                   |
|--------------|----------------|-------------------------------|
| hadir        | Hadir          | Guest will attend             |
| tidak_hadir  | Tidak Hadir    | Guest will not attend         |
| ragu         | Masih Ragu     | Guest is undecided            |


## Database Schema

Table: buku_tamus

| Column           | Type         | Nullable | Default | Description                    |
|------------------|--------------|----------|---------|--------------------------------|
| id               | BIGINT       | No       | Auto    | Primary key                    |
| user_id          | BIGINT       | No       | -       | Foreign key to users           |
| nama             | VARCHAR(100) | No       | -       | Guest name                     |
| email            | VARCHAR(100) | Yes      | NULL    | Guest email                    |
| telepon          | VARCHAR(20)  | Yes      | NULL    | Guest phone                    |
| ucapan           | TEXT         | Yes      | NULL    | Guest message/wishes           |
| status_kehadiran | ENUM         | No       | ragu    | Attendance status              |
| jumlah_tamu      | INT          | No       | 1       | Number of guests attending     |
| is_approved      | BOOLEAN      | No       | true    | Moderation status              |
| ip_address       | VARCHAR(45)  | Yes      | NULL    | Client IP address              |
| user_agent       | VARCHAR(255) | Yes      | NULL    | Client user agent              |
| created_at       | TIMESTAMP    | No       | -       | Record creation time           |
| updated_at       | TIMESTAMP    | No       | -       | Record last update time        |

Indexes:
- PRIMARY KEY (id)
- INDEX (user_id, status_kehadiran)
- INDEX (user_id, is_approved)
- INDEX (created_at)
- FOREIGN KEY (user_id) REFERENCES users(id)
