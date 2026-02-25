# Changelog

Todos los cambios relevantes de este proyecto serán documentados en este archivo.

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
- Integración de Web Awesome 3.2.1 en componentes de mantenimiento
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
- Web Awesome 3.2.1
- Tailwind CSS 4.x
