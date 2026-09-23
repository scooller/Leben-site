# AGENTS.md - Leben Project

## Executive Summary

**Leben** is a backend-first platform built with **Laravel 12** + **Filament 5**, specializing in sales management for real estate development projects with Salesforce integration, payment processing, and real-time data synchronization.

Current documented version: 1.9.28 (2026-09-23).

The application supports:

- **Administrative Panel** (Filament) for managing projects, units/floor plans ("plantas"), sales advisors ("asesores"), and contact leads
- **Public REST API** for external consumer integrations (WordPress, custom frontends, PHP sites)
- **Bidirectional Synchronization** with Salesforce (leads, cases, advisors)
- **Payment Processing** (Transbank, Mercado Pago)
- **Dynamic QR Codes** for sales advisors
- **Multi-channel Contact Forms**
- **Short Links** with visit and conversion tracking

---

***

## Mandatory Directives (EVERY session, no exceptions)

1. **Ponytail always ON** — before writing ANY code, read `.agents/skills/ponytail/SKILL.md` (or `~/.copilot/installed-plugins/ponytail/ponytail/skills/ponytail/SKILL.md`) and apply it: simplest solution, YAGNI first, stdlib/native before deps, one line before fifty. Never over-engineer.
2. **Answer in caveman mode** — load `.agents/skills/caveman/SKILL.md` and respond with `/caveman` style: terse, compressed, technically complete. Less tokens, same accuracy.
3. **Graphify before/after code** — `graphify-out/graph.json` exists, so:
   - BEFORE answering codebase questions: `rtk graphify query "<question>"`
   - Relationships: `rtk graphify path "<A>" "<B>"` / `rtk graphify explain "<concept>"`
   - AFTER modifying code: `rtk graphify update .` — always, no excuses.
4. **RTK prefix** — every terminal command runs as `rtk <command>` when available.
5. **tgrep for search** — use `tgrep` as the primary CLI search tool for fast codebase searching (classes, functions, strings, configs). Ripgrep-compatible syntax: `tgrep "query"`, `tgrep "query" -g "*.jsx"`.
6. **Build** — `rtk npm run build:all`, never bare `npm run build`.
7. **Version on every change** — WHENEVER project files are modified:
   - Bump `version` in `package.json` (semantic: patch = fix/refactor, minor = feature, major = breaking).
   - Add an entry to `CHANGELOG.md` (Keep a Changelog format) describing the changes.
   - Both must stay in sync (same version in `package.json` and `CHANGELOG.md`).

***

## Agent Roles (CaveCrew)

All agent personas are defined in `.agents/`. Each agent has a specific role and **must not** act outside its scope.

### 🔨 Builder — `.agents/cavecrew-builder.md`

- Responsible for **writing, generating, and modifying code**.
- Reads skills from `.agents/skills/` before implementing any feature.
- Follows project workflows defined in `.agents/workflows/`.
- Must check `.agents/docs/` for architectural decisions before coding.

### 🔍 Investigator — `.agents/cavecrew-investigator.md`

- Responsible for **research, analysis, and information gathering**.
- Reads `.agents/docs/` as primary source of truth.
- Reports findings in structured Markdown format.
- Does **not** modify code — only produces reports or recommendations.

### 🔎 Reviewer — `.agents/cavecrew-reviewer.md`

- Responsible for **code review, quality assurance, and validation**.
- Uses `.github/ISSUE_TEMPLATE/` to report issues found during review.
- Triggers or references `.github/workflows/` for CI validation.
- Must flag any deviation from standards defined in `.agents/docs/`.

***

### Agent Guidance by Stack

- If Laravel is detected, prefer Laravel conventions, service classes, validation layers, migrations, queues, and config-driven development.
- If WordPress is detected, prefer hooks, template hierarchy, plugin/theme separation, and WordPress coding standards.
- If React or Vite is detected, preserve the current component structure, build scripts, and asset pipeline.
- In React projects, always prefer Redux Toolkit (RTK) for global state, slices, async flows, and store structure unless the task explicitly requires another solution.
- If graph, node-based, relationship, or visual data flow features are required, always prefer Graphify as the first-choice library or pattern unless the repository already standardizes a different tool.
- If jQuery is present, do not remove it unless the task explicitly includes refactoring.
- If Bootstrap is present, reuse its utility and component system before adding custom UI patterns.

***

## Skills

Reusable skill modules are located in `.agents/skills/`.

- Before implementing any feature, the **Builder** agent **must** check if a relevant skill exists.
- Skills are composable — multiple skills can be combined in a single workflow.
- To add a new skill, create a `.md` file in `.agents/skills/` following the existing naming convention.

***

## Project Status (Recent Commits)

### Recently Completed Work

- ✅ **Git & Workspace Cleanliness**: Ignored Language Server Protocol temporary files (`lsp-*.php`), test caches, OS artifacts, and IDE configs in `.gitignore`.
- ✅ **Salesforce OAuth — Proactive Refresh**: Integrated proactive token refresh (`isTokenExpiringSoon()`, `proactiveRefresh()`, `executeWithTokenProtection()`) before token expiration; updated scheduler to run every 45 minutes; visual expiration countdown & status badges in Filament SiteSettings.
- ✅ **Projects API — precio_desde & tipologias**: Computed fields `precio_desde` (minimum `precio_lista` among active units) and `tipologias` (grouping by `programa`/`programa2`/`tipo_producto`) on `GET /api/v1/proyectos` and `GET /api/v1/proyectos/{id}` using optimized SQL `GROUP BY`.
- ✅ **Bulk Salesforce Sync**: Filament table bulk action to sync multiple contact records to Salesforce.
- ✅ **Salesforce Field Filtering**: Lead payload dynamically filtered against creatable Salesforce fields.
- ✅ **OAuth Salesforce**: Authentication and callback flow with token cache backup.
- ✅ **Connection Status Notifications**: Visual connection state indicators for Salesforce in Filament.
- ✅ **QR Codes**: Generation and display in the Advisors table.
- ✅ **Stage Normalization**: Conversion and slugification of project development stages (`proyecto_etapa`).
- ✅ **Website Preview**: Secure preview links and normalized URLs.
- ✅ **Dynamic Settings**: Configurable plants-per-page for API responses.
- ✅ **CSV Contact Submissions Import**: Filament wizard with progress tracking, per-channel mapping, and historical dry-run traceability.
- ✅ **Canonical Import Normalization**: Unified `rango_renta` and `apellido` handling with legacy alias cleanup.
- ✅ **Phone Number Normalization**: Persistence of phone numbers strictly formatted as digits.
- ✅ **Robust UTM Mapping**: Marketing UTM aliases mapped consistently to DB columns and Salesforce payloads.
- ✅ **Dynamic Pricing**: Implementation of unit maximum discounts (`descuento_maximo_unidad`) applied in Plants and Projects APIs.
- ✅ **Discount Configuration**: `SiteSettings` controls to select active Salesforce discount sources for pricing calculations.
- ✅ **Filament UX**: Integration of `filament-dual-scroll` for wide tables and improved column toggle visibility.
- ✅ **Extended Salesforce Mapping**: Support for `descuento_maximo_unidad` and `SalesforceCaseMapper` channel-to-website resolution.
- ✅ **Project Normalization**: Refined `Proyect_ID__c` synchronization and legacy column handling.
- ✅ **OAuth Token Hardening**: Explicit WebServer OAuth scope/prompt and retry limiters for `invalid_grant` errors.
- ✅ **Salesforce Queue Protection**: `CreateSalesforceCaseJob` avoids burning retry attempts when OAuth is disconnected.
- ✅ **API Security — token.origin**: Middleware validates API origin tokens using `PersonalAccessToken::findToken()` without relying on session state.
- ✅ **API Security — site-config**: Sensitive gateway configurations (`payment_gateways.*.config`, `price_source`, `price_percentage_source`) hidden in public responses; visible only with valid origin tokens.
- ✅ **Salesforce OAuth Auto-reconnect**: Encrypted OAuth token backups in DB (`SiteSetting.extra_settings`), automatically restoring cached tokens after `cache:clear` or Redis restarts.
- ✅ **Artisan Command `salesforce:refresh-token`**: Scheduled background worker (`cron('*/45 * * * *')`) performing proactive refresh or database backup synchronization.
- ✅ **Panel — Inactive Projects Visible**: Project selector in SiteSettings displays inactive projects with an `[Inactivo]` prefix instead of omitting them.
- ✅ **Reinforced Queue Auto-reconnection**: `CreateSalesforceCaseJob` attempts silent reconnect when cache tokens are absent or disconnected.
- ✅ **Sincronización de Asesores desde Producción**: Inclusión de asesores en `/api/v1/production-sync/export`, descarga e importación ordenada (`site_settings` -> `projects` -> `advisors` -> `plants`), mapeo pivote `asesor_proyecto` y vinculación `asesor_id` en plantas.
- ✅ **Orden Natural en Tablas de Plantas**: Ordenación numérica real en `name` (`21, 202, 1001`) y `piso` en `PlantsTable` y `PlantasRelationManager`; orden numérico con nulos al final para precios y descuentos.
- ✅ **Modal y Flujo de Sincronización desde Producción**: `SyncFromProductionAction` en Filament con ejecución síncrona o en cola, compatibilidad loopback para `EnsureTokenOriginIsAuthorized`, detección de timeout en `ProductionSyncProgress` y comando Artisan `production:sync`.
- ✅ **Reseteo Masivo de Plantas Sale**: Acción de cabecera `ResetSalePlantsAction` en `ListPlants` para desmarcar masivamente la bandera `unidad_sale`.
- ✅ **Protocolos de Descubrimiento para Agentes IA**: Endpoints ACP (`/.well-known/acp.json`), UCP (`/.well-known/ucp`), MPP (`/openapi.json`), Auth.md (RFC 8414, RFC 9728), MCP Server Card (`/.well-known/mcp/server-card.json`), Agent Skills (`/.well-known/agent-skills/index.json`) y `llms.txt`.

### Active Modules

1. **Salesforce Integration** - Lead/Case synchronization, OAuth, SOQL caching, proactive token renewal
2. **Contact Submissions** - Public submission forms with validation, channel routing, and automated Salesforce dispatch
3. **Plant Management** - Real estate units ("plantas") with filters, pricing calculations, discounts, and floor plan media
4. **Asesor Management** - Sales reps with avatars, WhatsApp redirects, and dynamic QR codes
5. **Proyecto Management** - Development projects with normalized stages, advisor assignments, and associated units
6. **Short Links** - Short links with click tracking and UTM parameter propagation
7. **Payments** - Transbank Webpay Plus and Mercado Pago integrations with webhooks and transaction states
8. **Activity Logging** - Audit trails for synchronization and critical system events
9. **Production Sync** - Snapshot export and import across environments with live progress tracking and timeout control

---

## Directory Architecture

```
app/
├── Services/           # Domain and integration services
│   ├── Salesforce/    # SalesforceService, SalesforceCaseMapper, etc.
│   ├── ProductionSync/# ProductionSyncService, ProductionSyncProgressTracker
│   ├── Payment/       # Payment gateways (Transbank, MercadoPago)
│   ├── FinMail/       # Transactional email management
│   └── ShortLink/     # Short link generation and redirection
├── Models/            # Eloquent models (Asesor, Plant, Proyecto, etc.)
├── Jobs/              # Queue jobs (Salesforce sync, emails, bulk imports)
├── Http/
│   ├── Controllers/   # API and OAuth controllers
│   ├── Requests/      # Form Requests with validation rules
│   └── Resources/     # Eloquent API Resources
├── Filament/          # Administrative panel
│   ├── Resources/     # CRUD resources (forms, tables, actions)
│   ├── Pages/         # Custom Filament pages (SiteSettings, Import)
│   ├── Widgets/       # Dashboard widgets
│   └── Actions/       # Reusable table and record actions
├── Mail/              # Mailable classes
├── Observers/         # Model observers (audit trails, auto-sync)
└── Enums/             # Business enums (PaymentGateway, ReservationStatus, etc.)

database/
├── migrations/        # Database migrations
├── factories/         # Model factories for testing
└── seeders/          # Database seeders

frontend/             # React application (Vite-based)
tests/                # PHPUnit test suite (Feature and Unit)
routes/
├── api.php           # Public API routes (v1)
├── web.php           # Web routes, OAuth endpoints, short link redirects
└── console.php       # Console commands and scheduled tasks
```

---

## Core Models

| Model | Purpose | Key Features |
| ------- | --------- | -------------- |
| **Asesor** | Real estate sales representative | Avatar, WhatsApp redirection, QR code generation |
| **Plant** | Housing unit / floor plan within a project | Pricing, discounts, image assets, filters (floor, typology, status) |
| **Proyecto** | Real estate project development | Normalized stage, assigned advisors, associated units |
| **ContactSubmission** | Customer inquiry / lead form | Channel routing, input validation, Salesforce synchronization |
| **ContactChannel** | Channel definition (sale, info, customer service) | Behavior configuration, target website routing |
| **Payment** | Payment transaction record | Gateway (Transbank / Mercado Pago), transaction state, webhook payload |
| **ShortLink** | Shortened tracking URL | Target destination URL, visit counter, UTM tracking |
| **PlantReservation** | Temporary unit hold / reservation | Customer details, reservation status, expiration validation |
| **SiteSetting** | Dynamic global key-value configuration | API settings, payment credentials, encrypted OAuth backups |
| **FrontendPreviewLink** | Temporary frontend preview link | Secure token, automatic expiration |

---

## Key Services

### SalesforceService

```php
// Location: app/Services/Salesforce/SalesforceService.php
// Responsibilities:
- Cached SOQL queries (Cache::remember)
- Salesforce object-to-Eloquent model mapping
- Payload filtering against creatable Salesforce fields
- OAuth authentication and token lifecycle management
- isTokenExpiringSoon(): detects tokens nearing expiration
- proactiveRefresh(): refreshes tokens before silent expiration
- executeWithTokenProtection(): wraps calls with auto-refresh and reconnection fallbacks
- tryAutoReconnect(): restores tokens from DB backup and triggers Forrest::refresh()
- updateTokenBackup(): persists renewed access tokens back to DB SiteSettings
```

### PaymentGateway Services

```php
// Transbank: app/Services/Payment/TransbankService.php
// Mercado Pago: app/Services/Payment/MercadoPagoService.php
// Responsibilities:
- Creating payment transactions
- Processing incoming webhook notifications
- Updating payment records and triggering post-payment hooks
```

### ShortLink Service

```php
// Location: app/Services/ShortLink/ShortLinkService.php
// Responsibilities:
- Generating short hash identifiers
- Tracking clicks, user agents, and IP addresses
- Appending and preserving marketing UTM parameters
```

---

## Main API Endpoints

### Contacts

```
POST   /api/v1/contact-submissions       # Submit contact inquiry
GET    /api/v1/contact-submissions/:id   # Retrieve contact inquiry details
```

### Plants (Units / Floor Plans)

```
GET    /api/v1/plants                     # List units with pagination & filters
GET    /api/v1/plants/:id                 # Unit details
GET    /api/v1/plants/:id/advisors        # Assigned sales advisors for unit
```

### Advisors (Asesores)

```
GET    /api/v1/advisors                   # List sales advisors
GET    /api/v1/advisors/:id/shortlink     # QR code / short link for advisor
```

### Projects (Proyectos)

```
GET    /api/v1/projects                   # List projects (with precio_desde & tipologias)
GET    /api/v1/projects/:id               # Project details
GET    /api/v1/projects/:id/plants        # Associated units of project
```

### Payments

```
POST   /api/v1/payments                   # Initialize payment transaction
POST   /api/v1/payments/transbank/webhook # Transbank Webpay webhook
POST   /api/v1/payments/mercadopago/webhook # Mercado Pago webhook
```

---

## Configuration Variables (.env)

```ini
# Salesforce
SALESFORCE_USERNAME=
SALESFORCE_PASSWORD=
SALESFORCE_SECURITY_TOKEN=
SALESFORCE_CONSUMER_KEY=
SALESFORCE_CONSUMER_SECRET=
SALESFORCE_REDIRECT_URI=

# Payment Gateways
TRANSBANK_COMMERCE_CODE=
TRANSBANK_API_KEY=
MERCADOPAGO_TOKEN=
MERCADOPAGO_WEBHOOK_TOKEN=

# Short Links
SHORT_LINK_DOMAIN=

# API & Security
TURNSTILE_TOKEN=  # Cloudflare Turnstile CAPTCHA secret

# Cache
CACHE_DRIVER=redis  # Recommended for reliable Salesforce token caching
```

---

## Project Conventions

### Naming Conventions & Identifiers

- **Proyecto**: Uses `proyecto_etapa` (normalized slug, e.g., `"venta"`, `"pre_venta"`).
- **Asesor**: Identified by internal ID, email, or Salesforce User ID.
- **Plant / Planta**: Unit or typology within a real estate development.
- **Database**: `snake_case` table and column names.
- **Code**: `camelCase` variables/methods, `PascalCase` classes and enums.

### Primary Enums

```php
PaymentGateway::Transbank | MercadoPago
PaymentStatus::Pending | Approved | Failed | Refunded
ReservationStatus::Reserved | Cancelled | Completed
ShortLinkStatus::Active | Expired | Disabled
ContactChannel::Sale | Info | Complaint  // Configurable per channel
```

### Architectural Patterns

- **Services**: Handle third-party integrations (Salesforce, Transbank, Mercado Pago).
- **Jobs**: Handle asynchronous or heavy background operations (Salesforce sync, email delivery).
- **Observers**: Monitor model lifecycle events (audit logging, automated sync triggers).
- **Form Requests**: Centralize validation rules, sanitization, and custom error messages.
- **API Resources**: Format and structure JSON responses for external consumers.

---

## Testing

**Current test coverage:**

- Unit tests for Salesforce mapping (`SalesforceCaseMapper`, lead field filtering, proactive refresh)
- Feature tests for public API endpoints (projects, plants, advisors, contact submissions)
- Feature tests for stage normalization and query filters
- Feature tests for payment webhooks and short link redirection

**Running tests:**

```bash
php artisan test --compact                             # Run full test suite
php artisan test --compact tests/Feature/ExampleTest.php # Run specific test file
php artisan test --compact --filter=testMethodName      # Run specific test method
```

---

## Recent Changes by Functional Area

### Salesforce Integration

- Bulk sync action in the ContactSubmissions table
- Dynamic creatable field filtering for Leads
- Proactive token renewal (`isTokenExpiringSoon()` and `proactiveRefresh()`) before silent expiration
- Centralized exception and reconnect handling in `executeWithTokenProtection()`
- Encrypted token backups in `SiteSetting.extra_settings` (`token_cache_backup`, `refresh_token_cache_backup`)
- Scheduled task (`salesforce:refresh-token`) running every 45 minutes

### Contacts & Leads

- Multi-channel validation (sale, info, customer service)
- Automated queued dispatch to Salesforce
- Rate limiting protection (`throttle:10,1`)
- CSV import wizard with pre-mapping channel selection and progress dashboard
- Dynamic column mapping with legacy alias support and phone digit sanitation
- Historical normalization command `contact:normalize-rango-renta-key` with `--dry-run`

### Plants & Projects

- Normalized development stages (`proyecto_etapa`)
- Computed pricing: `precio_desde` (minimum active unit price) and `tipologias` aggregation
- Unit discount caps (`descuento_maximo_unidad`) with dynamic pricing rules
- Configurable pagination and discount sources via `SiteSettings`

### UI & Filament Panel

- Centralized custom styling
- `filament-dual-scroll` integration for wide table UX
- QR code preview and download in Advisors table
- Website preview links with token expiration
- Inactive project display with `[Inactivo]` prefix in selectors

---

## Current Operational State

### Salesforce OAuth — Auto-reconnect & Proactive Refresh

**Status**: Implemented and verified with automated tests.

**Workflow**:

1. Following successful OAuth authorization, `SalesforceOAuthController::callback()` persists `forrest_token` and `forrest_refresh_token` into `SiteSetting.extra_settings.salesforce_oauth`.
2. `CreateSalesforceCaseJob` first verifies `isSalesforceOAuthMarkedDisconnected()` (fast-fail check); if tokens are missing from cache, it calls `tryAutoReconnect()`.
3. `tryAutoReconnect()` restores tokens to application cache and invokes `Forrest::refresh()`, fetching a new access token without user intervention.
4. Scheduled worker `salesforce:refresh-token` runs every 45 minutes to proactively refresh tokens before expiration or re-sync database backups.

**Post-deployment requirement**: Connect Salesforce OAuth once via `/admin/site-settings` to seed the initial database backup tokens.

**Operational Commands**:

- `php artisan salesforce:refresh-token` — Proactively refreshes nearing-expiration tokens or synchronizes DB backups.

---

### Contact Submissions — CSV Import

**Status**: Implemented and verified with automated tests.

**Capabilities**:

- Multi-step import wizard with mandatory channel selection before mapping.
- Background progress monitoring in the administrative panel.
- Dry-run verification mode to test CSV parsing without database persistence.
- Canonical field normalization (`rango_renta`, `apellido`, digits-only `phone`).
- Automatic UTM tagging and Salesforce payload enrichment.

**Operational Commands**:

- `php artisan contact:normalize-rango-renta-key --dry-run`
- `php artisan contact:normalize-rango-renta-key`

---

## Primary Dependencies

```json
{
  "filament/filament": "5.0",
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.3",
  "omniphx/forrest": "^2.20",          // Salesforce OAuth & REST client
  "mercadopago/dx-php": "^3.8",        // Mercado Pago SDK
  "transbank/transbank-sdk": "^5.1",   // Transbank SDK
  "lara-zeus/qr": "^3.0",              // QR code generator
  "spatie/laravel-permission": "^7.3", // Role-based access control
  "finity-labs/fin-mail": "*"          // Transactional email management
}
```

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16
- filament/filament (FILAMENT) - v5
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
  - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow existing conventions for how and where it's implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices.

### Artisan

- Use Filament-specific Artisan commands to create files. Find them with `list-artisan-commands` or `php artisan --help`.
- Inspect required options and always pass `--no-interaction`.

### Patterns

Use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),
</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
</code-snippet>

Actions encapsulate a button with optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->form([
        TextInput::make('email')->email()->required(),
    ])
    ->action(fn (array $data, User $record): void => $record->update($data)),
</code-snippet>

### Testing

Authenticate before testing panel functionality. Filament uses Livewire, so use `livewire()` or `Livewire::test()`:

<code-snippet name="Filament Table Test" lang="php">
    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users)
        ->searchTable($users->first()->name)
        ->assertCanSeeTableRecords($users->take(1))
        ->assertCanNotSeeTableRecords($users->skip(1));
</code-snippet>

<code-snippet name="Filament Create Resource Test" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Test',
            'email' => 'test@example.com',
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(User::class, [
        'name' => 'Test',
        'email' => 'test@example.com',
    ]);
</code-snippet>

<code-snippet name="Testing Validation" lang="php">
    livewire(CreateUser::class)
        ->fillForm([
            'name' => null,
            'email' => 'invalid-email',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'email',
        ])
        ->assertNotNotified();
</code-snippet>

<code-snippet name="Calling Actions" lang="php">
    use Filament\Actions\DeleteAction;
    use Filament\Actions\Testing\TestAction;

    livewire(EditUser::class, ['record' => $user->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ListUsers::class)
        ->callAction(TestAction::make('promote')->table($user), [
            'role' => 'admin',
        ])
        ->assertNotified();
</code-snippet>

### Common Mistakes

**Commonly Incorrect Namespaces:**

- Form fields (TextInput, Select, etc.): `Filament\Forms\Components\`
- Infolist entries (for read-only views) (TextEntry, IconEntry, etc.): `Filament\Forms\Components\`
- Layout components (Grid, Section, Fieldset, Tabs, Wizard, etc.): `Filament\Schemas\Components\`
- Schema utilities (Get, Set, etc.): `Filament\Schemas\Components\Utilities\`
- Actions: `Filament\Actions\` (no `Filament\Tables\Actions\` etc.)
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

**Recent breaking changes to Filament:**

- File visibility is `private` by default. Use `->visibility('public')` for public access.
- `Grid`, `Section`, and `Fieldset` no longer span all columns by default.

</laravel-boost-guidelines>

***

## Workflows

### Agent Workflows — `.agents/workflows/`

These define step-by-step processes agents should follow for common tasks (e.g., feature development, bug fixing, code review). Always prefer an existing workflow over improvising a process.

### GitHub Actions — `.github/workflows/`

These are automated CI/CD pipelines. Agents **must not modify** these files unless explicitly instructed. Agents can **read** them to understand what validations run on PRs and commits.

***

## GitHub Issue Templates — `.github/ISSUE_TEMPLATE/`

When an agent needs to report a bug, request a feature, or log a finding:

1. Use the appropriate template from `.github/ISSUE_TEMPLATE/`.
2. Fill all required fields — do not submit incomplete issues.
3. Link the issue to the relevant workflow or skill if applicable.

***

## Operating Rules for All Agents

1. **Read before acting** — always consult `.agents/docs/` and the relevant agent `.md` file before starting a task.
2. **Detect the stack first** — inspect the codebase before assuming Laravel, WordPress, React, or another framework.
3. **Use existing skills** — never reinvent logic that already exists in `.agents/skills/`.
4. **Follow workflows** — use `.agents/workflows/` as the execution guide for tasks.
5. **Respect role boundaries** — Builder builds, Investigator researches, Reviewer reviews.
6. **Do not modify CI/CD** — `.github/workflows/` are protected; propose changes via PR only.
7. **Document everything** — any new skill, workflow, or agent role must have its own `.md` file.
8. **Use issue templates** — when logging findings or bugs, always use `.github/ISSUE_TEMPLATE/`.
9. **Preserve project conventions** — match the existing architecture, naming, style, and dependency choices unless instructed otherwise.
10. **Use RTK by default** — in React applications, global state and async data flows must default to Redux Toolkit unless the repository explicitly uses another standard.
11. **Use Graphify by default** — in graph-based or relationship-driven interfaces, Graphify is the preferred solution unless an existing project dependency already defines another tool.
12. **Use tgrep for searching** — `tgrep` is the preferred search CLI across the codebase; use it proactively before modifying code.

***
