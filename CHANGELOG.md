# Changelog

Todos los cambios relevantes de este proyecto serán documentados en este archivo.

## [Unreleased]

### ✨ Agregado

#### Permisos y Control de Acceso (Spatie)
- Integración de `spatie/laravel-permission` para control de acceso granular
- Rol `marketing` con acceso restringido a recursos específicos del panel
- Roles y permisos registrados en el seeder principal

#### Canal de Contacto (ContactChannel)
- Nuevo modelo `ContactChannel` con recurso Filament completo
- Tabla `contact_channels` con campos: nombre, código, icono, color, estado
- Sincronización de canales desde Salesforce con migración idempotente
- Badges de color dinámicos usando `Filament\Support\Colors\Color`
- Resource con List, Create, Edit y filtros de estado

#### Actividad de Usuarios
- Página personalizada `UserActivitiesPage` en Filament para visualizar el log de actividad por usuario
- Vista Blade dedicada con historial detallado de acciones
- Tests de cobertura para la página y vista

#### Identidad Visual Modo Oscuro
- Campo `logo_dark_id` con `CuratorPicker` en SiteSettings (tab Branding)
- `AdminPanelProvider` carga logo claro u oscuro según el modo activo del panel
- Tests de cobertura para la selección dinámica de logo

#### Acceso y Validación
- Campo RUT en el formulario de usuario con validación de formato chileno
- Eliminación de rol duplicado al registrar usuarios
- Restricción de acceso al panel administrativo según permisos Spatie

---

## [1.8.0] - 2026-04-14

### ✨ Agregado

#### Short Links
- Nuevo modelo `ShortLink` con recurso Filament completo (List, Create, Edit)
- Rutas cortas configurables con slug, destino, estado y etiquetas UTM
- Acción `ExportShortLinksAction` con nombre nullable para exportaciones genéricas
- Infer automático de `utm_site` desde el request y mapeo al campo `Website` en Salesforce
- Icono de cursor outline en navegación de recursos

#### Salesforce — UI y Sincronización
- URL de lead de Salesforce visible como acción de vista rápida en submissions
- Columnas dinámicas de Salesforce expuestas en la tabla de submissions
- Acción de re-sincronización forzada de leads desde la tabla del panel
- Mejoras de UI en el formulario de submissions (defaults de slug, tag manager)

#### Frontend
- Poster para el video hero en la página principal
- Disclaimers configurables visibles en la sección hero
- Columna toggles habilitados en tablas para mayor flexibilidad operativa
- Tweaks de analítica en frontend con deduplicación de eventos

#### Pasarelas de Pago
- Columna `commerce_code` renombrada para mayor consistencia
- Link manual de pago configurable por pago en Filament
- Lógica de preferencia: el código de comercio persistido tiene prioridad sobre configuración

#### Logging
- Niveles de log ajustados a `debug` con lógica env-aware para no saturar producción

---

## [1.7.0] - 2026-04-07

### ✨ Agregado

#### SEO
- Tab SEO en SiteSettings con meta título, descripción, keywords, og:image
- Frontend React consume y aplica configuración SEO desde `siteConfig` context
- Tags Open Graph y meta description generados dinámicamente

#### Catálogo Público y Preview
- API de catálogo completamente pública (sin autenticación requerida)
- Middleware `CatalogPreviewMiddleware` para acceso de preview con token
- Campo `preview_path` en proyectos para generar URLs de preview
- Links de preview en el panel con token de acceso temporal
- Tests de cobertura para el middleware y tokens

#### Facturación y Pago
- Campos de facturación (`billing_name`, `billing_rut`, `billing_address`, etc.) en el flujo de pago
- Reutilización de datos del usuario pagador para pre-llenar campos de facturación
- Página pública de estado y resultado del pago sin autenticación
- No sobreescribir email/RUT del usuario al actualizar desde el formulario de pago

#### Cloudflare Turnstile
- Integración de Cloudflare Turnstile como captcha en el formulario de contacto
- Validación server-side del token Turnstile

#### MercadoPago Webhooks
- Verificación de firma en webhooks de Mercado Pago
- Mejoras en el procesamiento del formulario de contacto post-pago

#### Scripts de Header/Footer
- Inyección de scripts de header y footer configurables desde SiteSettings
- Precarga de `site-config` para evitar inserción doble de scripts
- Timeout configurable para el fetch de configuración
- Deduplicación de eventos Facebook Pixel

#### UTM y Rastreo
- Respeto de `utm_campaign` por defecto configurado en SiteSettings
- Soporte para legacy `auto-tagging` de UTM campaign
- Resolución del teléfono del asesor del proyecto para contacto
- Preferencia de teléfono y nombre del lead sobre datos legacy

---

## [1.6.0] - 2026-03-31

### ✨ Agregado

#### Salesforce — Flujo de Leads
- El formulario de contacto ahora crea Leads en lugar de Cases
- Reintentos automáticos de Lead ante campos inválidos con mapeo de UTMs
- Eliminación de campos no escribibles en el payload de Salesforce
- Resolución del ID de Salesforce del proyecto en el payload del lead
- Logging detallado de operaciones Salesforce

#### Formulario de Contacto — Enriquecimiento
- Campo de rango de ingresos (`income_range`) en el formulario de contacto
- Manejo de `project_types` por opción con rangos selectables
- Lógica condicional basada en proyectos en lugar de categorías
- Campos de ingresos e inversión agregados al mapper de Salesforce
- Soporte para aliases de campo y normalización de tokens
- RUT y datos de proyecto enviados al mapper de Salesforce

#### Catálogo y Curator
- Toggle `mostrar_plantas` en SiteSettings para mostrar/ocultar el catálogo
- Textos de catálogo no disponible configurables con RichEditor
- Curación de Curator y toggle de UI del catálogo
- `is_active` preservado en sincronización de proyectos

#### Preview y Tokens
- Generación de URLs de preview con token firmado
- Links de preview frontend con token de acceso
- Eliminación de la acción de activación masiva

#### QueuePendingList
- Widget `QueuePendingList` en el dashboard para monitorear trabajos pendientes en cola

#### Exporter
- `ContactSubmissionsExporter` con acción de exportación CSV

---

## [1.5.0] - 2026-03-24

### ✨ Agregado

#### GTM y Analítica
- Integración de Google Tag Manager configurable desde SiteSettings
- Soporte para Facebook Pixel en el Tag Manager
- Filtros de catálogo por slug de proyecto y comuna
- Ruta `/f` para filtros de catálogo
- Mejoras en el formulario de contacto con enhancements de tracking

#### Transbank Mall — Estabilización
- Persistencia de pagos Transbank y manejo completo de webhooks de retorno
- Logging detallado del flujo de checkout
- Bridge de redirección para el retorno de Transbank
- Validación del código de tienda (mall child code)
- Correcciones de lógica de comercio y favicon
- Soporte para errores HTTP globales en modo Mall
- Corrección de parámetros y mejora de manejo de errores

#### Tipo de Producto en Plantas
- Campo `tipo_producto` en el modelo `Plant`
- Normalización y filtros de `tipo_producto` en API y Filament
- Filtro con tests de cobertura para tipo de producto

#### Configuración Salesforce
- Tab de configuración de sincronización Salesforce en SiteSettings
- Filtro de sincronización de plantas por proyecto y tipo de producto

#### UI y Operación
- Logo de venta configurable desde SiteSettings y aplicado en frontend
- HtmlCodeEditor disponible como componente de formulario
- Manejo del cierre del dialog de plantas en frontend

---

## [1.4.0] - 2026-03-17

### ✨ Agregado

#### Sumisiones de Contacto
- Tabla `contact_submissions` con campos de venta y unidad
- Filtros de `unidad_sale` y activación masiva en la tabla de Filament
- Tokens de monto y moneda de reserva en templates de notificaciones

#### FinMail — Notificaciones de Email
- Integración del plugin `fin-mail` para notificaciones de correo transaccional
- Migraciones de tablas de email (`fin_mail_*`)
- Logging de actividad de negocio vinculado a eventos del sistema
- Wrap del registro del scheduler en try/catch para mayor estabilidad

#### UI de Plantas (Frontend)
- Interfaz actualizada del detalle de planta con estado de carga
- Asesores de proyecto expuestos en el payload de planta y en la UI
- Sincronización de imágenes de planta, branding y asesores desde Salesforce
- Soporte para ScrollSmoother en PlantsGrid
- Animaciones alternadas en las tarjetas de plantas
- Sello de descuento y refactoring del detalle de planta

#### Exportaciones y Pagos Manuales
- `ExportAction` habilitado para usuarios, pagos y plantas
- Soporte para pagos manuales con referencia libre
- API de disponibilidad de plantas mejorada

---

## [1.3.0] - 2026-03-10

### ✨ Agregado

#### Web Awesome 3.4.0
- Migración al paquete `@web.awesome.me` con versión 3.4.0
- Soporte actualizado para componentes React de Web Awesome

#### Filtros de Ubicación
- Filtros por región y comuna en la API de proyectos y catálogo
- Mejoras de UI en Filament para filtros de proyectos y plantas

---

## [1.2.0] - 2026-03-03

### ✨ Agregado

#### Sincronización de Plantas — Mejoras
- Job `SyncPlantsJob` con tests de cobertura
- Columna de imagen de portada visible en la tabla de plantas del panel
- Payloads de imagen de plantas en tests
- Timeout de reserva configurable con validación de UI

#### API de Plantas — Filtros y Documentación
- Filtros avanzados en la API de plantas (tipo, estado, proyecto, región)
- Endpoint de documentación de la API v1
- Tests de cobertura para filtros y documentación

#### Autenticación de API
- Gestión de API tokens en el panel Filament
- Middleware de origen (`origin`) para control de acceso
- Autenticación requerida para endpoints públicos de API

#### Activity Log
- `ActivityLogAuthorization` como middleware para restringir el log por perfil
- Seeder actualizado para incluir el permiso de activity log

#### Pruebas y Entorno
- Aislamiento con `sqlite_testing` para suite de tests
- Payloads de imágenes de plantas en escenarios de prueba

---

## [1.1.0] - 2026-02-28

### ✨ Agregado

#### Transbank Mall — Implementación
- `TransbankService` migrado de Simple a Mall (`WebpayMall`)
- Soporte para múltiples códigos de comercio por proyecto via `TRANSBANK_STORE_CODES` JSON
- `createTransaction()` con `commerce_code_store` dinámico resuelto desde el proyecto
- `confirmTransaction()` valida `commerceCodeStore` en respuesta vs proyecto
- Configuración en `config/payments.php` con `mall_mode` y `commerce_codes`
- Filament UI para gestión de códigos de comercio por proyecto

#### Sistema de Reservas de Plantas
- Tabla `plant_reservations` con timeout configurable
- Job `ExpireReservations` para expirar reservas vencidas
- API de disponibilidad considera reservas activas y pagos completados
- Dialog de confirmación de reserva en frontend

#### PaymentGatewayDialog
- Componente React `PaymentGatewayDialog` integrado en Home
- Soporte para múltiples gateways desde un único dialog
- Dashboard con orden de widgets configurable

#### API de Monitoreo
- Widgets de monitoreo de API en el dashboard con tests
- Endpoint de documentación de API v1
- Autenticación requerida para endpoints administrativos

### 🔄 Cambios

#### Configuración del Proyecto
- Archivos de bootstrap cacheados removidos del tracking de git
- `TRANSBANK_STORE_CODES` como variable de entorno JSON para mapeo proyecto↔código
- Pruebas migradas a SQLite para aislamiento

---

## [1.0.0] - 2026-02-25

### ✨ Agregado

#### Gestión Centralizada de Medios (Filament Curator)
- Instalación e integración de `awcodes/filament-curator` v1.x
- Tabla `curator` para registro de archivos con metadata
- File Manager accesible en `/admin/media`
- CuratorPicker para todos los campos de imagen:
  - Logo principal (`logo_id`)
  - Logo modo oscuro (`logo_dark_id`)
  - Favicon (`favicon_id`)
  - Ícono/Isotipo (`icon_id`)
  - Banner promocional (`banner_image_id`)
- Integración de `AttachCuratorMediaPlugin` en RichEditor para mantenimiento
- CropperJS para edición de imágenes
- Glide token generado para transformaciones de imagen

#### Sistema de Mantenimiento Avanzado
- RichEditor WYSIWYG para editar mensajes de mantenimiento
- Toggle para cambiar entre vista enriquecida y HTML plano
- Web Awesome `<wa-dialog>` component (reemplaza overlay custom)
- Prevención de cierre de diálogo mientras maintenance_mode está activo
- Gestión de clase `mantencion` en document.body
- Attachment de imágenes via Curator en mensaje de mantenimiento
- Campo `maintenance_use_html` para modo HTML

#### UI/UX
- Banner promocional con imagen y link configurables
- Integración de Web Awesome 3.4.0 en componentes del frontend y mantenimiento
- 11 temas Web Awesome disponibles en configuración
- Estilos SCSS para maintenance dialog con Web Awesome
- Soporte para múltiples paletas de colores

#### Estructuras de Base de Datos
- Migración: `2026_02_25_122347_add_transbank_commerce_code_to_proyectos.php`
  - Campo `transbank_commerce_code` en proyectos para multiples códigos comerciales
- Migración: `2026_02_25_124607_add_banner_fields_to_site_settings.php`
  - Campos `banner_image` y `banner_link`
- Migración: `2026_02_25_135153_add_maintenance_use_html_to_site_settings.php`
  - Campo `maintenance_use_html` (boolean)
- Migración: `2026_02_25_140351_create_curator_table.php` (auto-generada)
  - 17 columnas de metadata para archivos
  - Soporte para tenant awareness
- Migración: `2026_02_25_142024_add_curator_media_ids_to_site_settings.php`
  - Columnas `logo_id`, `logo_dark_id`, `icon_id`, `favicon_id`, `banner_image_id`
  - Foreign keys a tabla `curator` con `onDelete('set null')`

#### Backend (Laravel/Filament)
- SiteSetting modelo:
  - Relaciones `belongsTo(Media)` para todos los campos de imagen
  - Método `forFrontend()` que carga URLs directas desde Curator Media
  - Actualizacion de fillable para nuevos campos `*_id`
- AdminPanelProvider actualizado:
  - Carga relaciones `logoMedia` y `faviconMedia`
  - Favicon y logo ahora usan URLs de Curator en lugar de disco branding
  - Registro de CuratorPlugin con grupo 'Sistema' y sort 98
- SiteSettings Filament Page:
  - 9+ tabs de configuración global
  - Branding tab: Todos los campos de imagen usan CuratorPicker
  - Banner tab: Banner image via CuratorPicker
  - Mantenimiento tab: RichEditor con AttachCuratorMediaPlugin
  - Validaciones en Toggle de mantenimiento
  - HTML mode toggle con lógica de dehydration

#### Frontend (React)
- Componente MaintenanceMode:
  - Web Awesome `<wa-dialog>` (modal behavior)
  - Uso de `dialog.open` property (no métodos deprecated)
  - Event listener en `wa-hide` para prevenir cierre
  - Gestión de clase `mantencion` en body
  - HTML rendering via `dangerouslySetInnerHTML`
- Componente BannerPromo:
  - Renderizado de banner image
  - Click handling para links (internal/external)
- App.jsx: Integración de MaintenanceMode
- Home.jsx: Integración de BannerPromo antes de hero

#### Build & Tooling
- Creación de `tailwind.config.js`
  - Content paths para Filament y Curator views
  - Asegura que Tailwind procese clases de Curator
- theme.css actualizado:
  - Imports para Filament, Curator, CropperJS CSS
  - @source directives para scanning de Filament y Curator componentes
- Vite buildeo exitoso con todos los assets compilados
- Pint formatting configurado y funcionando

### 🔄 Cambios

#### Model Updates
- `Proyecto` model: Removidas relaciones innecesarias con PaymentPlan
- `SiteSetting` model: Migracion de relaciones de disco a Curator Media
- Proyectos Form schema reorganizado en 2 secciones principales

#### Admin Panel
- Logo y favicon ahora cargan desde Curator en lugar de disco branding
- Todos los campos de imagen migrados de FileUpload a CuratorPicker
- RichEditor cambió de attachments en disco a Curator Media attachments

#### Frontend
- Maintenance overlay reemplazado por Web Awesome dialog component
- Dialog styling con SCSS personalizado para Web Awesome
- Body class management implementado para estado de mantenimiento

### 🐛 Fixes

- Filament 5 import issues: Section moved from Forms → Schemas namespace
- RichEditor fileAttachmentsModel() method removed (no existe en Filament 5)
- CuratorPicker field binding fix: Usar `_id` suffix para foreign keys
- AdminPanelProvider: Removida referencia al disco branding innecesario
- Dialog close prevention: Previene cierre de wa-dialog via event listener

### 🗑️ Removido

- CustomOverlay CSS component (reemplazado por Web Awesome dialog)
- FileUpload components en SiteSettings (reemplazados por CuratorPicker)
- Relaciones de disco branding en favor de Curator Media
- Método `fileAttachmentsDisk/Directory/Visibility` en RichEditor

### 📦 Dependencias Agregadas

```json
{
  "awcodes/filament-curator": "^1.x"
}
```

Instaladas automáticamente:
- `crop/cropper`: ^1.6.2 (CropperJS)
- `league/php-mime-type-detection`: ^1.x
- Otros dependencies de Curator

### 📝 Documentación

- README.md completamente reescrito con:
  - Stack tecnológico detallado
  - Arquitectura de carpetas
  - Características principales
  - Guía de instalación completa
  - Comandos comunes
  - Estructura de base de datos
  - Convenciones de código
- CHANGELOG.md creado (este archivo)

### 🔐 Seguridad

- Foreign keys en `site_settings` → `curator` con `onDelete('set null')`
- Escenarios de cascada considerados en migraciones
- HTML sanitization pasada en RichEditor via Filament

### ✅ Testing

- 5 migraciones ejecutadas exitosamente
- Frontend buildeo sin errores (Exit Code 0)
- Pint formatting pasó (todos los builds)
- Errores pre-existentes ignorados (ProjectPaymentPlanResource)

### 🎯 Casos de Uso

**Flujo de Upload de Logo:**
1. Admin va a `/admin/site-settings` → Branding tab
2. Hace click en CuratorPicker para logo
3. Sube imagen desde File Manager o carga existente
4. Imagen se registra en tabla `curator`
5. `logo_id` se guarda en `site_settings`
6. Relación `logoMedia()` resuelve la URL
7. Logo aparece en admin panel y frontend automáticamente

**Flujo de Attachment en Maintenance Message:**
1. Admin activa `maintenance_mode`
2. Escribe mensaje en RichEditor
3. Hace click en botón "Attach Curator Media"
4. Selecciona imagen del File Manager `/admin/media`
5. Imagen se inserta en el contenido HTML
6. Se registra en tabla `curator`
7. Frontend renderiza con Web Awesome dialog

### 🚀 Deployment

Antes de deployar a producción:
1. Ejecutar `php artisan migrate --force`
2. Verificar que `storage/` está writable
3. Ejecutar `php artisan storage:link`
4. Ejecutar `php artisan filament:cache-components`
5. Build frontend: `cd frontend && npm run build && cd ..`
6. Ejecutar `php artisan optimize`

---

## Notas para Desarrolladores

### Estructura de Migrations
Las migraciones se ejecutan en orden cronológico. Verificar que las FK están en orden:
- `2026_02_25_140351_create_curator_table.php` debe ejecutarse antes de
- `2026_02_25_142024_add_curator_media_ids_to_site_settings.php`

### Troubleshooting

**Logo no carga en admin:**
- Verificar que `logo_id` tiene valor en DB
- Verificar que relación `logoMedia()` está configurada
- Ejecutar `php artisan optimize:clear`

**Attachment en RichEditor no aparece:**
- Verificar que `AttachCuratorMediaPlugin::make()` está registrado
- Verificar que toolbar buttons include `'attachCuratorMedia'`
- Comprobar que tabla `curator` tiene registros

**Styles de Curator incompletos:**
- Ejecutar `npm run build` para compilar tailwind
- Verificar content paths en `tailwind.config.js`
- Limpiar caché: `php artisan optimize:clear`

### Próximas Mejoras Potenciales

- [ ] Integración con Glide para transformaciones de imagen dinámicas
- [ ] Variant storage para diferentes tamaños de imagen
- [ ] Soft delete para archivos en Curator
- [ ] Auditoría de cambios en SiteSettings
- [ ] Webhook para sincronización en tiempo real
- [ ] API endpoint para obtener SiteSettings (authenticated)

---

**Stack Versions Utilizadas:**
- Laravel 12.49.0
- Filament 5.x
- PHP 8.4.16
- React 19.x
- Web Awesome 3.4.0
- Tailwind CSS 4.x
