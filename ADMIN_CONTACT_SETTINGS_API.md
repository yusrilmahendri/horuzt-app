# Admin Contact Settings API

API endpoints for managing admin contact settings in Horuzt wedding invitation platform.

## Base URL
`/api/v1/admin/contact-settings`

## Authentication
All endpoints require Bearer token authentication with admin role.

Headers:
```
Authorization: Bearer {token}
```

## Endpoints

### 1. Get Contact Settings
**GET** `/v1/admin/contact-settings`

Retrieves the single admin contact settings record.

**Response 200 OK**
```json
{
  "success": true,
  "message": "Contact settings retrieved successfully",
  "data": {
    "id": 1,
    "host_email": "zayyin.alfar1@gmail.com",
    "email": "zayyin.alfar1@gmail.com",
    "whatsapp": "08123456789",
    "whatsapp_token": "720f91719af7632f1783305a6fbb1c0b657297",
    "whatsapp_message": "Hello Admin Ganteng ,Saya Mau bertanya.",
    "created_at": "2025-10-13T12:00:00.000000Z",
    "updated_at": "2025-10-13T12:00:00.000000Z"
  }
}
```

**Response 404 Not Found**
```json
{
  "success": false,
  "message": "No contact settings found",
  "data": null
}
```

### 2. Update Contact Settings
**PUT** `/v1/admin/contact-settings`

Updates or creates the admin contact settings record. All fields are optional.

**Request Body**
```json
{
  "host_email": "zayyin.alfar1@gmail.com",
  "email": "zayyin.alfar1@gmail.com",
  "whatsapp": "08123456789",
  "email_password": "password123",
  "whatsapp_token": "720f91719af7632f1783305a6fbb1c0b657297",
  "whatsapp_message": "Hello Admin Ganteng ,Saya Mau bertanya."
}
```

**Field Validation**
- `host_email`: nullable, email format, max 255 characters
- `email`: nullable, email format, max 255 characters
- `whatsapp`: nullable, string, max 255 characters
- `email_password`: nullable, string (hidden in responses)
- `whatsapp_token`: nullable, string
- `whatsapp_message`: nullable, string

**Response 200 OK (Update)**
```json
{
  "success": true,
  "message": "Contact settings updated successfully",
  "data": {
    "id": 1,
    "host_email": "zayyin.alfar1@gmail.com",
    "email": "zayyin.alfar1@gmail.com",
    "whatsapp": "08123456789",
    "whatsapp_token": "720f91719af7632f1783305a6fbb1c0b657297",
    "whatsapp_message": "Hello Admin Ganteng ,Saya Mau bertanya.",
    "created_at": "2025-10-13T12:00:00.000000Z",
    "updated_at": "2025-10-13T12:30:00.000000Z"
  }
}
```

**Response 201 Created (First Time)**
```json
{
  "success": true,
  "message": "Contact settings created successfully",
  "data": {
    "id": 1,
    "host_email": "zayyin.alfar1@gmail.com",
    "email": "zayyin.alfar1@gmail.com",
    "whatsapp": "08123456789",
    "whatsapp_token": "720f91719af7632f1783305a6fbb1c0b657297",
    "whatsapp_message": "Hello Admin Ganteng ,Saya Mau bertanya.",
    "created_at": "2025-10-13T12:00:00.000000Z",
    "updated_at": "2025-10-13T12:00:00.000000Z"
  }
}
```

**Response 422 Unprocessable Entity**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "host_email": [
      "The host email must be a valid email address."
    ]
  }
}
```

### 3. Delete Contact Settings
**DELETE** `/v1/admin/contact-settings`

Deletes the admin contact settings record.

**Response 200 OK**
```json
{
  "success": true,
  "message": "Contact settings deleted successfully"
}
```

**Response 404 Not Found**
```json
{
  "success": false,
  "message": "No contact settings found"
}
```

## Notes

1. System stores only one contact settings record.
2. All fields are optional.
3. `email_password` field is hidden in responses for security.
4. Update endpoint creates record if none exists.
5. Only admin role can access these endpoints.

## Implementation Files

- Model: [app/Models/AdminContactSetting.php](app/Models/AdminContactSetting.php)
- Controller: [app/Http/Controllers/Admin/AdminContactSettingController.php](app/Http/Controllers/Admin/AdminContactSettingController.php)
- Routes: [routes/api.php](routes/api.php:150-155)
- Migration: [database/migrations/2025_10_13_034623_create_admin_contact_settings_table.php](database/migrations/2025_10_13_034623_create_admin_contact_settings_table.php)
