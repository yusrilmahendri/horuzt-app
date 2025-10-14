# User Contact Settings API

API endpoint for users to retrieve admin contact information for the Contact Us feature.

## Base URL
`/api/v1/user/contact-settings`

## Authentication
Requires Bearer token authentication with user role.

Headers:
```
Authorization: Bearer {token}
Accept: application/json
```

## Endpoint

### Get Contact Settings
**GET** `/v1/user/contact-settings`

Retrieves admin contact information for user display.

**Request Example**
```bash
curl -X GET http://127.0.0.1:8000/api/v1/user/contact-settings \
  -H "Authorization: Bearer {your_user_token}" \
  -H "Accept: application/json"
```

**Response 200 OK**
```json
{
  "success": true,
  "message": "Contact settings retrieved successfully",
  "data": {
    "email": "admin@example.com",
    "whatsapp": "08123456789",
    "whatsapp_message": "Hello Admin, saya mau bertanya."
  }
}
```

**Response 404 Not Found**
```json
{
  "success": false,
  "message": "Contact settings not available",
  "data": null
}
```

**Response 401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

## Field Descriptions

- `email`: Admin contact email address
- `whatsapp`: Admin WhatsApp number
- `whatsapp_message`: Pre-filled message template for WhatsApp contact

## Usage Notes

1. Read-only access for users
2. Sensitive fields (tokens, passwords) are hidden
3. Returns single contact settings record
4. Use this data for Contact Us page or contact forms
5. Admin manages settings via admin endpoints

## Security

Protected fields not exposed to users:
- host_email
- email_password
- whatsapp_token
- Database metadata (id, timestamps)

## Implementation

- Controller: `app/Http/Controllers/ContactSettingController.php`
- Model: `app/Models/AdminContactSetting.php`
- Routes: `routes/api.php` (line 349-351)
- Middleware: `auth:sanctum`, `role:user`

## Related Documentation

For admin management of contact settings, see [ADMIN_CONTACT_SETTINGS_API.md](ADMIN_CONTACT_SETTINGS_API.md)
