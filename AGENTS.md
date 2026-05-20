# AGENTS.md - Proyecto Leben

## Resumen Ejecutivo

**Leben** es una plataforma backend-first construida con **Laravel 12** + **Filament 5**, especializada en la gestión de ventas de proyectos inmobiliarios con integración Salesforce, procesamiento de pagos y sincronización de datos en tiempo real.

La aplicación soporta:
- **Panel administrativo** (Filament) para gestión de proyectos, plantas, asesores y contactos
- **API REST** pública para integraciones externas (WordPress, PHP, etc.)
- **Sincronización bidireccional** con Salesforce (leads, casos, asesores)
- **Gestión de pagos** (Transbank, Mercado Pago)
- **QR codes** dinámicos para asesores
- **Formularios de contacto** multi-canal
- **Short links** con tracking de visitas

---

## Estado del Proyecto (Últimos 30 commits)

### Trabajo Completado Recientemente
- ✅ **Bulk Salesforce Sync**: Acción para sincronizar múltiples registros a Salesforce
- ✅ **Filtrado de campos Salesforce**: Payload de Lead filtrado por campos creables
- ✅ **OAuth Salesforce**: Autenticación y flujo de callback con caché
- ✅ **Notificaciones de conexión**: Indicadores visuales de estado Salesforce
- ✅ **QR Codes**: Generación y gestión en tabla de Asesores
- ✅ **Normalización de Etapas**: Conversión de etapas de proyectos (proyecto_etapa)
- ✅ **Website Preview**: Links de vista previa y normalización de URLs
- ✅ **Settings dinámicos**: Configuración de plantas por página en API
- ✅ **Importación CSV de contactos**: Progreso en panel, mapeo por canal y trazabilidad del proceso
- ✅ **Normalización canónica de importación**: `rango_renta` y `apellido` unificados con limpieza de aliases legacy
- ✅ **Normalización de telefonía**: Persistencia de teléfonos en formato solo dígitos
- ✅ **UTM mapping robusto**: Aliases de marketing homologados hacia campos UTM y payload Salesforce
- ✅ **Precios Dinámicos**: Implementación de descuentos máximos por unidad (descuento_maximo_unidad) con lógica de aplicación en API de Plantas y Proyectos
- ✅ **Configuración de Descuentos**: Ajustes en `SiteSettings` para definir fuentes de descuento Salesforce para pricing de la API
- ✅ **UX en Panel**: Implementación de `filament-dual-scroll` para tablas extensas y mejoras en visibilidad de columnas de contactos
- ✅ **Mapeo Salesforce Extendido**: Soporte para `descuento_maximo_unidad` y refinamiento en `SalesforceCaseMapper` (resolución de sitio web por canal)
- ✅ **Normalización de Proyectos**: Sincronización refinada de `Proyect_ID__c` y manejo de campos legacy
- ✅ **Precios Dinámicos**: Implementación de descuentos máximos por unidad (descuento_maximo_unidad) con lógica de aplicación en API de Plantas y Proyectos
- ✅ **Configuración de Descuentos**: Ajustes en `SiteSettings` para definir fuentes de descuento Salesforce para pricing de la API
- ✅ **UX en Panel**: Implementación de `filament-dual-scroll` para tablas extensas y mejoras en visibilidad de columnas de contactos
- ✅ **Mapeo Salesforce Extendido**: Soporte para `descuento_maximo_unidad` y refinamiento en `SalesforceCaseMapper` (resolución de sitio web por canal)
- ✅ **Normalización de Proyectos**: Sincronización refinada de `Proyect_ID__c` y manejo de campos legacy
- ✅ **Precios Dinámicos**: Implementación de descuentos máximos por unidad (descuento_maximo_unidad) con lógica de aplicación en API de Plantas y Proyectos
- ✅ **Configuración de Descuentos**: Ajustes en SiteSettings para definir fuentes de descuento Salesforce para pricing de la API
- ✅ **UX en Panel**: Implementación de filament-dual-scroll para tablas extensas y mejoras en visibilidad de columnas de contactos
- ✅ **Mapeo Salesforce Extendido**: Soporte para descuento_maximo_unidad y refinamiento en SalesforceCaseMapper (resolución de sitio web por canal)
- ✅ **Normalización de Proyectos**: Sincronización refinada de Proyect_ID__c y manejo de campos legacy

### Módulos Activos
1. **Salesforce Integration** - Sincronización de leads/casos, OAuth, caché
2. **Contact Submissions** - Formulario público con validación, canales y sincronización Salesforce
3. **Plant Management** - Plantas con filtros, precios y links
4. **Asesor Management** - Asesores con avatares, WhatsApp, QR codes
5. **Proyecto Management** - Proyectos con normalización de etapas y relaciones
6. **Short Links** - Links cortos con tracking y UTM
7. **Payments** - Transbank y Mercado Pago integrados con webhooks y estado de transacciones
8. **Activity Logging** - Registros de auditoría y sincronización

---

## Arquitectura de Directorios

```
app/
├── Services/           # Servicios de dominio
│   ├── Salesforce/    # SalesforceService, SalesforceCaseMapper, etc.
│   ├── Payment/       # Pasarelas de pago
│   ├── FinMail/       # Gestión de correos
│   └── ShortLink/     # Manejo de links cortos
├── Models/            # Modelos Eloquent (Asesor, Plant, Proyecto, etc.)
├── Jobs/              # Trabajos encolados
├── Http/
│   ├── Controllers/   # Controllers API y OAuth
│   ├── Requests/      # Form Requests con validación
│   └── Resources/     # API Resources
├── Filament/          # Panel administrativo
│   ├── Resources/     # Recursos de tablas/formularios
│   ├── Pages/         # Páginas customizadas
│   ├── Widgets/       # Widgets del dashboard
│   └── Actions/       # Acciones en tablas/registros
├── Mail/              # Mailable classes
├── Observers/         # Observadores de modelos
└── Enums/             # Enumeraciones (PaymentGateway, ReservationStatus, etc.)

database/
├── migrations/        # Migraciones de BD
├── factories/         # Factories para testing
└── seeders/          # Seeders

frontend/             # React app (separada, Vite)
tests/                # Tests PHPUnit
routes/
├── api.php           # API routes (v1)
├── web.php           # Web routes
└── console.php       # Comandos Artisan
```

---

## Modelos Principales

| Modelo | Propósito | Características |
|--------|-----------|-----------------|
| **Asesor** | Vendedor/representante de ventas | Avatar, WhatsApp redirect, QR code |
| **Plant** | Departamento/unidad inmobiliaria | Precio, imágenes, filters (piso, type) |
| **Proyecto** | Proyecto inmobiliario | Etapa normalizada, asesores, plantas |
| **ContactSubmission** | Formulario de contacto | Canal, validación, sincronización Salesforce |
| **ContactChannel** | Tipo de canal (sale, info, etc.) | Configuración de comportamiento |
| **Payment** | Registro de pago | Gateway (transbank/mercadopago), estado |
| **ShortLink** | Link corto para tracking | URL destino, visitas, UTM |
| **PlantReservation** | Reserva temporal de planta | Usuario, estado, validación |
| **SiteSetting** | Configuración global | Key-value dinámico |
| **FrontendPreviewLink** | Link de vista previa | Token, expira |

---

## Servicios Clave

### SalesforceService
```php
// Ubicación: app/Services/Salesforce/SalesforceService.php
// Responsabilidades:
- Consultas SOQL con caché (Cache::remember)
- Mapeo de objetos Salesforce a modelos Laravel
- Sincronización de campos filtrados
- Gestión de OAuth y tokens
```

### PaymentGateway Services
```php
// Transbank: app/Services/Payment/TransbankService.php
// Mercado Pago: app/Services/Payment/MercadoPagoService.php
// Responsabilidades:
- Crear transacciones
- Procesar webhooks
- Actualizar estado de pagos
```

### ShortLink Service
```php
// Generación de links cortos
// Tracking de visitas
// Construcción de URLs con UTM
```

---

## API Endpoints Principales

### Contactos
```
POST   /api/v1/contact-submissions       # Crear contacto
GET    /api/v1/contact-submissions/:id   # Ver contacto
```

### Plantas
```
GET    /api/v1/plants                     # Listar plantas
GET    /api/v1/plants/:id                 # Detalles planta
GET    /api/v1/plants/:id/advisors        # Asesores de planta
```

### Asesores
```
GET    /api/v1/advisors                   # Listar asesores
GET    /api/v1/advisors/:id/shortlink     # QR/Short link del asesor
```

### Proyectos
```
GET    /api/v1/projects                   # Listar proyectos
GET    /api/v1/projects/:id/plants        # Plantas del proyecto
```

### Pagos
```
POST   /api/v1/payments                   # Crear pago
POST   /api/v1/payments/transbank/webhook # Webhook Transbank
POST   /api/v1/payments/mercadopago/webhook # Webhook Mercado Pago
```

---

## Variables de Configuración (.env)

```ini
# Salesforce
SALESFORCE_USERNAME=
SALESFORCE_PASSWORD=
SALESFORCE_SECURITY_TOKEN=
SALESFORCE_CONSUMER_KEY=
SALESFORCE_CONSUMER_SECRET=
SALESFORCE_REDIRECT_URI=

# Pagos
TRANSBANK_COMMERCE_CODE=
TRANSBANK_API_KEY=
MERCADOPAGO_TOKEN=
MERCADOPAGO_WEBHOOK_TOKEN=

# Short Links
SHORT_LINK_DOMAIN=

# API
TURNSTILE_TOKEN=  # Cloudflare Turnstile para CAPTCHA

# Cache
CACHE_DRIVER=redis  # Recomendado para Salesforce
```

---

## Convenciones del Proyecto

### Nombres y Códigos
- **Proyecto**: Usa `proyecto_etapa` (slug normalizado, ej: "venta", "pre_venta")
- **Asesor**: Identificado por ID o email en Salesforce
- **Plant/Planta**: Unidad inmobiliaria dentro de proyecto

### Enums Principales
```php
PaymentGateway::Transbank | MercadoPago
PaymentStatus::Pending | Approved | Failed | Refunded
ReservationStatus::Reserved | Cancelled | Completed
ShortLinkStatus::Active | Expired | Disabled
ContactChannel::Sale | Info | Complaint  // Configurables
```

### Patrones de Código
- **Services** para lógica de integración (Salesforce, Pagos)
- **Jobs** para operaciones asincrónicas (sincronización, emails)
- **Observers** para eventos de modelos (auditoría, sincronización)
- **Form Requests** para validación con reglas complejas
- **API Resources** para formato de respuestas

---

## Testing

**Cobertura actual:**
- Tests unitarios para Salesforce (SalesforceCaseMapper, lead field cache)
- Tests de normalización de etapas
- Tests para validación de form requests

**Ejecutar tests:**
```bash
php artisan test --compact                    # Todos
php artisan test --compact tests/Feature/...  # Por file
php artisan test --compact --filter=testName  # Por test
```

---

## Cambios Recientes por Área

### Salesforce
- Bulk sync action en tabla ContactSubmissions
- Filtrado dinámico de campos creables (Lead)
- Manejo robusto de tokens expirados
- Caché ampliado para consultas SOQL
- OAuth con flujo authenticate/callback

### Contactos
- Validación por canal (sale, info, etc.)
- Sincronización automática a Salesforce
- Rate limiting (throttle:10,1)
- Importación CSV con selección de canal previa al mapeo
- Mapeo dinámico de columnas con aliases históricos y normalización consistente
- Comando de normalización histórica `contact:normalize-rango-renta-key` con `--dry-run`

### Plantas & Proyectos
- Normalización de etapas (proyecto_etapa)
- Filtros por piso, tipo
- Precios con descuento
- Ordenamiento dinámico

### UI/Frontend
- Estilos centralizados
- Upload de archivos con límite mayor
- Website preview links
- QR code en Asesores
- Ajustes de formato en Curator (`MediaForm`) y test de QR short link para mantener consistencia (sin cambios funcionales)

---

## Estado Operativo Actual

### Contact Submissions - Importación CSV
**Estado**: Implementado y validado con pruebas focalizadas

**Cobertura funcional activa**:
- Wizard de importación con canal seleccionado antes del mapeo
- Página de progreso de importación en panel administrativo
- Dry-run para validación previa sin persistencia
- Normalización de campos canónicos (`rango_renta`, `apellido`, `phone`)
- Mapeo y enriquecimiento UTM para integraciones de marketing/Salesforce

**Comando operativo**:
- `php artisan contact:normalize-rango-renta-key --dry-run`
- `php artisan contact:normalize-rango-renta-key`

---

## Dependencias Principales

```json
{
  "filament/filament": "5.0",
  "laravel/framework": "^12.0",
  "laravel/sanctum": "^4.3",
  "omniphx/forrest": "^2.20",          // Salesforce
  "mercadopago/dx-php": "^3.8",        // Mercado Pago
  "transbank/transbank-sdk": "^5.1",   // Transbank
  "lara-zeus/qr": "^3.0",              // QR codes
  "spatie/laravel-permission": "^7.3", // RBAC
  "finity-labs/fin-mail": "*"          // Email
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



