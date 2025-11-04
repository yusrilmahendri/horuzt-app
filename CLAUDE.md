# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 10-based wedding invitation (undangan) SaaS application. Users can create customized digital wedding invitations with themes, galleries, guest management, payment processing via Midtrans, and music streaming.

The project includes:
- Main Laravel app (root directory)
- `spatie-test/` - Isolated Laravel app for testing Spatie packages

## Development Commands

### Setup & Installation
```bash
# Install PHP dependencies
composer install

# Install JavaScript/CSS dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed database
php artisan db:seed
```

### Running the Application
```bash
# Start development server
php artisan serve

# Build assets (production)
npm run build

# Build assets (development with hot reload)
npm run dev
```

### Testing
```bash
# Run all tests
php artisan test

# Run tests with PHPUnit directly
vendor/bin/phpunit

# Run specific test
php artisan test --filter TestName
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Check code without fixing
./vendor/bin/pint --test
```

### Database Management
```bash
# Fresh migration (drops all tables)
php artisan migrate:fresh

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Rollback last migration
php artisan migrate:rollback

# Create new migration
php artisan make:migration create_table_name
```

### Clearing Caches
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan optimize
```

## Architecture Overview

### Core Business Domain

The application is built around wedding invitation management with these key entities:

**Invitations** - Central entity linking users to wedding packages. Tracks payment status, domain expiry, and package snapshots. See `app/Models/Invitation.php` for domain expiry logic and scopes (`activeDomains`, `expiredDomains`).

**Themes System** - Multi-level theme hierarchy:
- `CategoryThemas` - Top-level categories
- `JenisThemas` - Theme types within categories
- `ResultThemas` - Final rendered themes
- Custom theme components in `app/Models/Thema.php`

**Payment Flow** - Handled via Midtrans integration:
- Token generation: `MidtransController::createSnapToken()`
- Webhook processing: `MidtransController::handleWebhook()`
- See `MIDTRANS_API_FLOW.md` for detailed flow documentation
- Configuration can be per-user (from `midtrans_transactions` table) or global (from config/env)

**Wedding Profile Components**:
- `Mempelai` - Bride/groom information
- `Acara` - Event schedules with countdown
- `Galery` - Photo galleries
- `Cerita` - Love story sections
- `BukuTamu` - Guest book entries
- `Attendance` - RSVP tracking

### Service Layer

Located in `app/Services/`:

**MidtransService** - Handles Midtrans API integration, configuration loading (DB-first fallback to env), and Snap token generation.

**MusicStreamService** - Implements HTTP range request handling for audio streaming. Supports partial content delivery for better performance with large music files.

### Custom Middleware

Important middleware in `app/Http/Middleware/`:

- `LargeFileHandler` - Handles large file uploads
- `BypassPostSizeLimit` - Bypasses POST size limits for specific routes
- `CorsMiddleware` - Custom CORS handling

Routes using these: InvitationController's step endpoints (`/v1/two-step`, `/v1/three-step`) support large file uploads for galleries/music.

### Multi-Step Form Flow

The invitation creation uses a multi-step wizard pattern:
1. `/v1/one-step` - Basic info (package selection)
2. `/v1/two-step` - Media uploads (uses large file middleware)
3. `/v1/three-step` - Additional content (uses large file middleware)
4. `/v1/for-step` - Final configuration

### API Structure

All API routes in `routes/api.php`:
- Prefix: `/api/v1/`
- Public routes: Registration, login, theme browsing, guest features
- Protected routes: Use Sanctum authentication
- Webhook endpoint: `/v1/midtrans/webhook` (no auth, signature verified)

### Package Snapshots

When an invitation is created, package details are snapshotted (`package_price_snapshot`, `package_duration_snapshot`, `package_features_snapshot`) to preserve pricing even if packages change. This ensures users get what they paid for.

### Domain Expiry System

Invitations have time-limited domains based on package duration:
- `domain_expires_at` tracks expiry
- `isDomainActive()` checks if domain is still valid
- Scopes: `activeDomains()`, `expiredDomains()`
- Payment must be confirmed (`payment_status = 'paid'`) for domain to be active

### Role & Permissions

Uses Spatie Laravel Permission package (`spatie/laravel-permission`). Migration: `2024_09_16_115135_create_permission_tables.php`.

Separate admin controllers in `app/Http/Controllers/Admin/`:
- `AdminContactSettingController`
- `AdminBankAccountController`
- `SettingControllerAdmin`

## Important Files & Locations

### Configuration
- `config/midtrans.php` - Midtrans payment gateway settings
- `.env` - Environment-specific config (not committed)
- `.vscode/mcp.json` - AI/automation server configurations

### Documentation
- `MIDTRANS_API_FLOW.md` - Complete Midtrans integration documentation including security considerations, testing guide, and performance optimization recommendations

### Key Models
- `app/Models/Invitation.php` - Main invitation logic with domain expiry methods
- `app/Models/User.php` - User authentication
- `app/Models/MidtransTransaction.php` - Per-user Midtrans credentials
- `app/Models/Setting.php` - User-specific settings including music files

### Controllers Organization
- `app/Http/Controllers/` - Main controllers
- `app/Http/Controllers/Admin/` - Admin-specific controllers
- `app/Http/Controllers/Auth/` - Authentication controllers
- `app/Http/Controllers/Api/` - Additional API controllers

## Important Conventions

### Database Naming
Some inconsistencies exist (legacy):
- `methode_pembayaran` appears in some tables (should be `metode`)
- Always check existing field names before adding new migrations

### File Uploads
- Music files: Stored in `storage/app/` with path in `Setting::musik`
- Gallery images: Handled through multi-step upload process
- Use `Storage` facade for file operations

### Error Handling
When working with payment/webhook endpoints, always:
1. Log attempts (especially failures)
2. Validate signatures for webhooks
3. Use database transactions for payment status updates
4. Return appropriate HTTP status codes

### Spatie-Test Subproject
The `spatie-test/` directory is a separate Laravel installation for isolated package testing. When adding features related to Spatie packages, test them here first before integrating into main app.

## Testing Considerations

### Midtrans Testing
Use Midtrans sandbox credentials in `.env`:
- `MIDTRANS_IS_PRODUCTION=false`
- Test cards documented in `MIDTRANS_API_FLOW.md`

### Local Webhook Testing
Webhook endpoint requires signature verification. Generate test signatures using:
```php
hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey)
```

## Key Package Integrations

- **Laravel Sanctum** - API authentication
- **Spatie Laravel Permission** - Role/permission management
- **Midtrans PHP** - Payment gateway SDK
- **League CSV** - CSV handling (likely for guest imports/exports)
- **Guzzle** - HTTP client for external API calls
- **Vite** - Frontend asset bundling
