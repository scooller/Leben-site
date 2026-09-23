# iLeben - Plataforma backend-first para administración de proyectos y plantas

Aplicación backend-first construida con Laravel 12, Filament 5, React 19 y Web Awesome. El sistema opera como mantenedor administrativo de proyectos, plantas y asesores comerciales, con sincronización bidireccional contra Salesforce como sistema maestro, sincronización de datos desde entornos de producción, procesamiento de pagos con Transbank y Mercado Pago, y soporte para protocolos de descubrimiento comercial y agentes de inteligencia artificial.

## 📋 Stack Tecnológico

### Backend

- **Laravel 12** - Framework PHP moderno
- **Filament 5** - Panel administrativo SDUI
- **PHP 8.4** - Lenguaje de programación
- **MySQL 8** / **SQLite** (testing) - Bases de datos
- **Salesforce API** (`omniphx/forrest`) - Integración CRM bidireccional
- **Laravel Sanctum 4** - Autenticación API con validación de origen (`token.origin`)
- **Spatie Permission 7** - Control de acceso granular por roles (`admin`, `marketing`)

### Frontend

- **React 19** - UI library
- **Vite 6** - Build tool y pipeline de assets
- **Web Awesome Pro 3.7.0** - Design system y web components
- **Tailwind CSS 4** - Utility-first CSS
- **GSAP** - Animaciones interactivas
- **Cloudflare Turnstile** - Validación CAPTCHA server-side

### Gestión de Medios

- **Filament Curator 5** - Gestor centralizado de archivos/imágenes
- **CropperJS** - Editor y recorte de imágenes

### Pasarelas de Pago

- **Transbank Webpay Plus** - Pagos en Chile (soporte Mall y múltiples códigos de comercio)
- **Mercado Pago** - Pagos Latam con webhooks firmados
- **Manual** - Registro y subida de comprobantes con aprobación en panel

### Protocolos de Agentes de IA

- **ACP** (Agentic Commerce Protocol)
- **UCP** (Universal Commerce Protocol)
- **MPP** (Machine Payment Protocol)
- **Auth.md** (RFC 8414 & RFC 9728)
- **MCP** (Model Context Protocol Server Card)
- **Agent Skills Discovery** & **ARD**

---

## 🏗️ Arquitectura

```
app/
├── Models/                 # Eloquent Models (Proyecto, Plant, Asesor, Payment, etc.)
├── Filament/
│   ├── Resources/          # CRUD Resources (Plants, Proyectos, Asesores, etc.)
│   ├── Pages/              # Custom Pages (SiteSettings, ProductionSyncProgress)
│   ├── Actions/            # Reusable Actions (SyncFromProductionAction, ResetSalePlantsAction)
│   └── Widgets/            # Dashboard Widgets
├── Services/
│   ├── Salesforce/         # SalesforceService, SalesforceCaseMapper, OAuth Lifecycle
│   ├── ProductionSync/     # ProductionSyncService, ProductionSyncProgressTracker
│   ├── Payment/            # TransbankService, MercadoPagoService, PaymentGatewayManager
│   └── ShortLink/          # Generación y redirección con UTMs
├── Jobs/                   # Queue Jobs (SyncPlantsJob, RunProductionSyncJob, CreateSalesforceCaseJob)
├── Http/
│   ├── Controllers/        # API, Webhooks, Redirects, OAuth
│   ├── Middleware/         # EnsureTokenOriginIsAuthorized, MaintenancePreview
│   └── Resources/          # API Resources JSON
├── Support/                # FlowLogMatrix, SalesforcePlantSyncSchedule
├── Enums/                  # PaymentGateway, PaymentStatus, ReservationStatus
└── Contracts/              # Interfaces

resources/
├── css/                    # Estilos Filament + Tailwind CSS v4
└── views/                  # Vistas Blade (pagos, emails, sitemaps)

frontend/
├── src/
│   ├── components/         # Componentes React y Web Awesome
│   ├── pages/              # Catálogo, Detalle, Checkout, Resultado
│   ├── contexts/           # SiteConfigContext
│   ├── services/           # Clientes API (plants, checkout, reservations)
│   └── utils/              # TagManager, SEO, validaciones
└── dist/                   # Build compilado para producción
```

---

## 📦 Características Principales

### Operación y Sincronización

- ✅ **Sincronización desde Producción (Production Sync)**:
  - Exportación segura en `/api/v1/production-sync/export` mediante token Sanctum y validación `token.origin`.
  - Importación integral de `site_settings`, `projects`, `advisors` (con tabla pivote `asesor_proyecto`) y `plants` (con vinculación `asesor_id`).
  - Modal interactivo en Filament (`SyncFromProductionAction`) con ejecución síncrona o asíncrona en colas.
  - Pantalla de progreso en vivo (`ProductionSyncProgress`) con detección automática de timeout configurable.
  - Soporte de conexión loopback/localhost (`127.0.0.1`, `localhost`) para desarrollo local.
  - Comando Artisan `php artisan production:sync`.
- ✅ **Integración Bidireccional con Salesforce**:
  - Sincronización de proyectos, plantas y asesores comerciales.
  - Creación de Leads / Casos desde formularios de contacto con reintentos automáticos y mapeo UTM completo.
  - Filtrado dinámico de campos creables en Salesforce para prevenir fallos por campos de solo lectura.
  - Caching inteligente de consultas SOQL con TTL configurable.
  - **OAuth Proactivo y Auto-reconexión**: Renovación periódica antes del vencimiento (`salesforce:refresh-token` cada 45 min), respaldo cifrado de tokens en base de datos (`SiteSetting.extra_settings`) y restauración automática si se reinicia Redis o se ejecuta `cache:clear`.
  - Protección de colas: `CreateSalesforceCaseJob` evita quemar reintentos infinitos si OAuth está desconectado.
- ✅ **Normalización de Datos**:
  - Conversión canónica de etapas de proyecto (`proyecto_etapa`).
  - Teléfonos estandarizados a solo dígitos y rangos de renta normalizados (`contact:normalize-rango-renta-key`).

### Panel Administrativo (Filament 5)

- ✅ **Tablas y Orden Natural**:
  - Orden natural numérico en columnas de plantas (`name`: `21, 202, 1001`, `piso`).
  - Ordenación numérica con valores nulos posicionados al final en modo ascendente para precios (`precio_base`, `precio_lista`, `precio_final`) y porcentajes de descuento.
- ✅ **Gestión de Plantas y Unidades Sale**:
  - Botón de cabecera para sincronizar desde producción en el catálogo de plantas.
  - Acción masiva `ResetSalePlantsAction` para desmarcar unidades Sale con modal de confirmación.
  - Sincronización de imágenes de portada e interiores vía Curator.
- ✅ **Asesores Comerciales**:
  - Perfiles con teléfono, email, avatar y asignación a proyectos y plantas.
  - Generación dinámica de códigos QR y enlaces de redirección a WhatsApp con etiquetas UTM.
- ✅ **Configuración Global (SiteSettings - 11+ Tabs)**:
  - Branding (logos claro/oscuro, favicon, banner promocional).
  - Colores y temas Web Awesome (11 paletas preconfiguradas).
  - SEO, scripts header/footer, Google Tag Manager y Facebook Pixel con deduplicación de eventos.
  - Configuración de pasarelas de pago y credenciales.
  - Sincronización y estado en vivo de la conexión con Salesforce.
- ✅ **Herramientas de Auditoría y Seguridad**:
  - Historial detallado de actividad con Spatie Activity Log.
  - Log Viewer integrado y Command Runner para operaciones administrativas.
  - Integración de `filament-dual-scroll` para tablas anchas.

### Pasarelas de Pago y Reservas

- ✅ **Transbank Webpay Plus**: Soporte para Transbank Mall con códigos de comercio configurables por proyecto (`TRANSBANK_STORE_CODES`).
- ✅ **Mercado Pago**: Procesamiento seguro con verificación de firma criptográfica en webhooks.
- ✅ **Seguridad y Logs Limpios**: Sanitización automática de credenciales, tokens y payloads crudos en logs mediante `FlowLogMatrix`.
- ✅ **Flujo de Reservas**: Expiración automática de reservas huérfanas mediante scheduler (`reservations:expire` cada minuto).
- ✅ **Facturación y Comprobantes**: Pre-llenado de datos de facturación y subida de comprobantes en transferencias manuales.

### Protocolos de Agentes de IA y Descubrimiento Web

- ✅ **Agentic Commerce Protocol (ACP)**: Endpoint `/.well-known/acp.json` para descubrimiento de catálogo y reservas.
- ✅ **Universal Commerce Protocol (UCP)**: Endpoint `/.well-known/ucp` con capacidades de checkout y catálogo.
- ✅ **Machine Payment Protocol (MPP)**: Documento `/openapi.json` con extensiones `x-payment-info`.
- ✅ **Auth.md & RFCs**: Directrices de autenticación de agentes en `/auth.md`, `/ .well-known/oauth-authorization-server` (RFC 8414) y `/.well-known/oauth-protected-resource` (RFC 9728).
- ✅ **MCP Server**: Ficha de servidor en `/.well-known/mcp/server-card.json`.
- ✅ **Agent Skills**: Directorio `/.well-known/agent-skills/index.json` con skills para agentes autónomos.
- ✅ **LLMs & Sitemap**: Endpoints `/llms.txt`, `/.well-known/llms.txt`, `/sitemap.xml` dinámico y `/robots.txt` con señales para IA.

---

## 🛠️ Comandos Útiles

### Desarrollo y Diagnóstico

```bash
# Estado general del framework y configuración
php artisan about

# Listar todos los comandos disponibles
php artisan list

# Limpiar todas las cachés (config, rutas, vistas, eventos)
php artisan optimize:clear
```

### Sincronización desde Producción

```bash
# Sincronizar snapshot desde producción (usando configuración en .env)
php artisan production:sync

# Sincronizar indicando URL y credenciales de forma explícita
php artisan production:sync --base-url="https://admin.ileben.cl" --token="TU_TOKEN_SANCTUM" --authorized-url="http://127.0.0.1:8000/admin"
```

### Sincronización Salesforce

```bash
# Sincronizar catálogo de plantas con Salesforce
php artisan sync:plants

# Sincronizar proyectos desde Salesforce (test/manual)
php artisan test:sync-projects

# Refrescar token OAuth de Salesforce o sincronizar backup en DB
php artisan salesforce:refresh-token

# Probar autenticación y conectividad con Salesforce
php artisan salesforce:test-auth
```

### Scheduler Operativo (Laravel Schedule)

Comandos programados en `routes/console.php`:

| Tarea / Comando | Frecuencia | Descripción |
| --------------- | ---------- | ----------- |
| `reservations:expire` | Cada minuto | Libera reservas de plantas que superaron el tiempo de espera |
| `SyncPlantsJob` | Cada minuto (sin overlap) | Sincronización de plantas condicional según `SalesforcePlantSyncSchedule` |
| `salesforce:refresh-token` | Cada 45 min (sin overlap) | Renueva proactivamente el token OAuth antes de expirar |
| `model:prune --model=FrontendPreviewLink` | Diario | Limpia enlaces temporales de previsualización caducados |

#### Operación en Servidor / cPanel (Cron + Queue Worker)

Si `QUEUE_CONNECTION=database`, configurar dos tareas en el cron del sistema:

```bash
# 1) Scheduler Laravel (cada minuto)
* * * * * /usr/local/bin/php /home/usuario/laravel/artisan schedule:run >> /dev/null 2>&1

# 2) Worker de cola con lock anti-acumulación (cada minuto)
* * * * * /usr/bin/flock -n /tmp/leben-queue.lock /usr/local/bin/php /home/usuario/laravel/artisan queue:work database --queue=default --sleep=3 --tries=3 --backoff=5 --max-time=50 --stop-when-empty >> /home/usuario/laravel/storage/logs/queue-worker.log 2>&1
```

### Base de Datos y Normalización

```bash
# Ejecutar migraciones pendientes
php artisan migrate

# Estado de migraciones
php artisan migrate:status

# Simular normalización histórica de rangos de renta (dry-run)
php artisan contact:normalize-rango-renta-key --dry-run

# Ejecutar normalización histórica de rangos de renta
php artisan contact:normalize-rango-renta-key
```

### Pruebas Automatizadas

```bash
# Ejecutar suite completa de tests (PHPUnit)
php artisan test --compact

# Ejecutar un test unitario o feature específico
php artisan test --compact tests/Unit/Services/ProductionSyncServiceTest.php
php artisan test --compact tests/Feature/Filament/PlantsTableNaturalSortingTest.php
```

---

## 🌐 API REST y Protocolos

Base URL: `/api/v1`

### Endpoints Principales

- **Descubrimiento OpenAPI**: `GET /api/v1`
- **Configuración Pública**: `GET /api/v1/site-config` (oculta pasarelas sensibles si no proviene de un origen autorizado)
- **Formulario de Contacto**: `POST /api/v1/contact-submissions` (con rate limiting)
- **Proyectos**:
  - `GET /api/v1/proyectos` (incluye computados `precio_desde` y `tipologias`)
  - `GET /api/v1/proyectos/{id}` (parámetros `include_plantas`, `include_asesores`, `campos`)
- **Plantas (Unidades)**:
  - `GET /api/v1/plantas` (filtros: `proyecto_id`, `programa`, `piso`, `orientacion`, `disponible`, `evento_sale`, etc.)
  - `GET /api/v1/plantas/filtros-ubicacion` (regiones y comunas con proyectos activos)
  - `GET /api/v1/plantas/proyecto/{projectSlug}/unidad/{unitName}` (detalle por slug y nombre)
  - `GET /api/v1/plantas/{id}`
- **Reservas y Checkout**:
  - `POST /api/v1/checkout` (iniciar sesión de pago Transbank / Mercado Pago)
  - `POST /api/v1/reservations` (crear reserva)
  - `DELETE /api/v1/reservations/{sessionToken}` (liberar reserva)
  - `GET /api/v1/reservations/planta/{plantId}` (consultar estado de reserva)
- **Sincronización de Producción** (Protegido `auth:sanctum` + `token.origin`):
  - `GET /api/v1/production-sync/export` (exporta snapshot de configuración, proyectos, asesores y plantas)
- **Pagos y Comprobantes** (Protegido):
  - `POST /api/v1/payments`
  - `GET /api/v1/payments`
  - `POST /api/v1/payments/{id}/manual-proof`

Para detalles completos de cada área, consulta la documentación dedicada:

## 📚 Documentación Específica

- [Guía de API](API_USAGE.md) — Uso operativo de endpoints, autenticación, `token.origin` y ejemplos cURL.
- [Pagos & Pasarelas](PAYMENTS.md) — Sistema de pagos completo (Transbank Webpay Plus, Mercado Pago y transferencias manuales).
- [Frontend React](frontend/README.md) — Estructura, variables de entorno, scripts de build y prerenderizado de rutas.
- [Historial de Cambios](CHANGELOG.md) — Registro cronológico de versiones y novedades de la plataforma.

---

## 🚀 Instalación y Puesta en Marcha

### Requisitos

- PHP 8.2+ (Recomendado **PHP 8.4**) con extensiones: `pdo_mysql`, `curl`, `mbstring`, `openssl`, `bcmath`, `fileinfo`
- Composer 2.x
- Node.js 18+ (con npm)
- MySQL 8.0+

### Pasos Iniciales

```bash
# 1. Clonar repositorio
git clone <repo-url> back-ileben
cd back-ileben

# 2. Instalar dependencias PHP
composer install

# 3. Configurar entorno
cp .env.example .env
php artisan key:generate

# 4. Configurar base de datos y Salesforce en .env
# DB_DATABASE=...
# SF_CONSUMER_KEY=...
# SF_CONSUMER_SECRET=...

# 5. Ejecutar migraciones
php artisan migrate

# 6. Crear enlace simbólico de almacenamiento
php artisan storage:link

# 7. Compilar frontend / panel
npm run build:all

# 8. Iniciar entorno de desarrollo
composer dev
# O de manera individual:
# php artisan serve
# npm run dev
```

---

## 🗄️ Base de Datos

### Tablas Principales

- `users` — Usuarios del panel y clientes autenticados.
- `site_settings` — Configuración global (singleton `ID=1`), opciones visuales y backups OAuth.
- `curator` — Biblioteca de medios y archivos centralizada.
- `proyectos` — Proyectos inmobiliarios con etapas homologadas y códigos de comercio.
- `plants` — Departamentos/plantas con tipologías, imágenes, precios y estado Sale.
- `asesors` — Asesores de venta con avatar, teléfono WhatsApp y QR dinámico.
- `asesor_proyecto` — Tabla pivote que vincula asesores con múltiples proyectos.
- `payments` — Historial de transacciones de pago y estados.
- `plant_reservations` — Bloqueos temporales de unidades durante el checkout.
- `contact_submissions` — Formularios de contacto y sincronización a Salesforce Leads/Casos.
- `contact_channels` — Canales comerciales con badges y reglas de enrutamiento.
- `short_links` / `short_link_visits` — Enlaces cortos con tracking de visitas y UTMs.
- `exports` — Cola de exportaciones de Filament.
- `frontend_preview_links` — Enlaces seguros de previsualización temporal.
- `activity_log` — Registro de auditoría de acciones administrativas.

---

## 🔐 Seguridad

- ✅ **Protección CSRF y Rate Limiting** en endpoints públicos y autenticación.
- ✅ **Sanctum + `token.origin`**: Validación estricta de dominios autorizados para tokens Bearer.
- ✅ **Sanitización de Payloads**: Prevención de fugas de datos sensibles en logs de pasarelas.
- ✅ **Verificación de Firmas en Webhooks** (Transbank y Mercado Pago).
- ✅ **Sanitización HTML** en componentes RichEditor de Filament.
- ✅ **Prevención de Expiración OAuth**: Renovación proactiva y respaldos cifrados en base de datos.

---

## 📄 Licencia

Todos los derechos reservados — iLeben © 2026

**Última actualización:** 2026-09-23  
**Versión:** 1.9.28  
**[Historial de cambios](CHANGELOG.md)**
