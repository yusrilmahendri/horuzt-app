# Copilot Instructions for horuzt-app

## Project Overview
- This is a Laravel-based PHP monorepo with a main app and a `spatie-test` subproject, both following standard Laravel structure.
- The main app is in the root; `spatie-test` is a sibling Laravel app for testing or package development.
- Core logic lives in `app/` (Controllers, Models, Middleware, etc.), with routes in `routes/` and views in `resources/views/`.
- Configuration is in `config/`, environment variables in `.env` (not committed).

## Key Workflows
- **Install dependencies:**
  - PHP: `composer install`
  - JS/CSS: `npm install`
- **Run the app:**
  - `php artisan serve` (main app)
  - `php artisan serve` in `spatie-test/` for the subproject
- **Run tests:**
  - `php artisan test` or `vendor/bin/phpunit` (main app)
  - Same in `spatie-test/`
- **Build assets:**
  - `npm run build` (uses Vite)

## Project Conventions
- **Models:** All Eloquent models are in `app/Models/`.
- **Controllers:** HTTP controllers in `app/Http/Controllers/`.
- **Migrations/Seeders:** In `database/migrations/` and `database/seeders/`.
- **Custom Artisan commands:** In `app/Console/Commands/`.
- **API routes:** In `routes/api.php`; web routes in `routes/web.php`.
- **Tests:** Feature and unit tests in `tests/Feature/` and `tests/Unit/`.
- **Spatie-test:** Mirrors main app structure for isolated package or feature testing.

## Integration & Extensions
- **MCP (Model Context Protocol):**
  - `.vscode/mcp.json` configures MCP servers for AI/automation workflows.
  - Custom servers: `everything`, `sequential-thinking`, `fetch`, etc.
  - See `.vscode/mcp.json` for details and extension points.
- **External services:**
  - Integrations (e.g., Notion, GitHub, Postman) are present but commented out in `.vscode/mcp.json`.

## Patterns & Tips
- Follow Laravel conventions unless a local pattern is clearly established.
- For new features, mirror the structure in both main app and `spatie-test` if package isolation/testing is needed.
- Use `.vscode/mcp.json` as the source of truth for AI/automation server config.
- When in doubt, check the main app first, then look for overrides or experiments in `spatie-test/`.

## References
- Main app: `app/`, `routes/`, `config/`, `resources/`, `tests/`
- Spatie-test: `spatie-test/app/`, `spatie-test/routes/`, etc.
- MCP config: `.vscode/mcp.json`

---
If any conventions or workflows are unclear, ask for clarification or check for updates in this file or the main README.
