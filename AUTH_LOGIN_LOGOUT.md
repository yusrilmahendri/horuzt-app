# Authentication System

Login and logout functionality for Horuzt wedding invitation platform.

## Controller Location

[LoginController.php](app/Http/Controllers/Auth/LoginController.php)

## Authentication Flow

### Login Process

User submits email and password to `/api/v1/login`.
System validates credentials against database.
Laravel Sanctum generates API token.
Token returns to client with user role.

### Logout Process

User sends authenticated request to `/api/v1/logout`.
System deletes current access token from database.
Token becomes invalid.
User needs new token to access protected routes.

## Login Implementation

**Endpoint**: `POST /api/v1/login`

**Required Fields**:
- email (string, valid email format)
- password (string)

**Process Steps**:

1. Request validation runs
2. System checks credentials with `Auth::attempt()`
3. Returns 401 if credentials invalid
4. Retrieves authenticated user
5. Creates Sanctum token via `createToken()`
6. Fetches user roles from Spatie Permission
7. Returns token and role data

**Response Format**:
```json
{
  "access_token": "token_string",
  "token_type": "Bearer",
  "role": ["user"]
}
```

## Logout Implementation

**Endpoint**: `POST /api/v1/logout`

**Requirements**:
- Valid Bearer token in Authorization header
- `auth:sanctum` middleware protection

**Process Steps**:

1. Extracts current access token from request
2. Deletes token from `personal_access_tokens` table
3. Clears token cookie if exists
4. Returns success message

**Response Format**:
```json
{
  "message": "Successfully logged out",
  "status": true
}
```

## Token System

**Technology**: Laravel Sanctum

**Storage**: Database table `personal_access_tokens`

**Token Lifecycle**:
- Created at login
- Stored in database with user reference
- Sent to client as plain text (one time only)
- Client stores token
- Client sends token in Authorization header
- Token validates on each protected route
- Token deleted at logout

## Protected Routes

Routes use `auth:sanctum` middleware.
Middleware checks token validity before allowing access.

**User Routes**: `auth:sanctum` + `role:user`
**Admin Routes**: `auth:sanctum` + `role:admin`

Examples:

```php
// User only
Route::group(['middleware' => ['auth:sanctum', 'role:user']], function () {
    Route::get('/v1/user/paket-nikah', [PaketController::class, 'index']);
});

// Admin only
Route::group(['middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/v1/admin/get-users', [UserController::class, 'index']);
});
```

## Role System

**Package**: Spatie Laravel Permission

**Implementation**: User model includes `HasRoles` trait

**Role Check**: `$user->getRoleNames()` returns collection of role names

**Available Roles**:
- admin
- user

## Security Features

**Password Hashing**: Automatic via Laravel (bcrypt)

**Token Generation**: Random 40-character string via Sanctum

**Credential Verification**: `Auth::attempt()` compares hashed passwords

**Token Deletion**: Physical removal from database at logout

**Middleware Protection**: Unauthorised requests blocked before reaching controller

## Authentication Config

**File**: [config/auth.php](config/auth.php)

**Default Guard**: web (session-based)

**API Guard**: sanctum (token-based)

**User Provider**: Eloquent

**User Model**: [App\Models\User](app/Models/User.php)

## Database Requirements

**Tables**:
- `users` (stores user data)
- `personal_access_tokens` (stores Sanctum tokens)
- `model_has_roles` (stores user role assignments)
- `roles` (stores role definitions)

## Client Usage

**Login Request**:
```
POST /api/v1/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

**Protected Route Request**:
```
GET /api/v1/user/paket-nikah
Authorization: Bearer {token}
```

**Logout Request**:
```
POST /api/v1/logout
Authorization: Bearer {token}
```

## Error Responses

**Invalid Credentials** (401):
```json
{
  "message": "Invalid login details"
}
```

**Unauthorised Access** (401):
Laravel returns default unauthorised response when token invalid or missing.

**Validation Error** (422):
Laravel returns validation errors when email or password format incorrect.

## Key Components

**LoginController Methods**:
- `login()` - handles authentication
- `logout()` - handles token deletion

**User Model Traits**:
- `HasApiTokens` - enables Sanctum tokens
- `HasRoles` - enables role management

**Middleware**:
- `auth:sanctum` - validates tokens
- `role:user` - checks user role
- `role:admin` - checks admin role

## Token Behaviour

Tokens persist until logout.
No automatic expiration.
One token per login session.
Multiple devices need multiple tokens.
Old tokens deleted when user logs out from specific device.

## References

File locations:
- Controller: [app/Http/Controllers/Auth/LoginController.php](app/Http/Controllers/Auth/LoginController.php:12-49)
- Routes: [routes/api.php](routes/api.php:52) (login), [routes/api.php](routes/api.php:105) (logout)
- User Model: [app/Models/User.php](app/Models/User.php:27-29)
- Auth Config: [config/auth.php](config/auth.php:16-19)
