# Changelog

Todos los cambios relevantes de este proyecto serán documentados en este archivo.

## [Unreleased]

## [1.9.35] - 2026-09-24

### 🔑 Revelación Segura de Clave de Tokens API con Reautenticación de Contraseña

- **Base de Datos y Modelo (`database/migrations/...`, `app/Models/PersonalAccessToken.php`)**:
  - Agregada columna `encrypted_token` en `personal_access_tokens` con cifrado simétrico AES-256 nativo mediante el cast `'encrypted'`.
  - Configurado `Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class)` en `AppServiceProvider`.
- **Panel Filament (`app/Filament/Resources/ApiTokens/`)**:
  - `ListApiTokens`: Persiste el `plainTextToken` cifrado en `encrypted_token` al momento de su creación.
  - `ApiTokensTable`: Incorporada acción de registro `Ver Key` (`viewKey`) con modal de confirmación que valida la contraseña del administrador actual (`currentPassword()`) antes de revelar la clave en texto plano.
  - Manejo amigable para tokens heredados/legacy generados previamente sin respaldo cifrado.
- **Pruebas Automatizadas (`tests/Feature/ApiTokenManagementTest.php`)**:
  - Creada suite de pruebas unitarias/feature cubriendo creación con respaldo cifrado, bloqueo ante contraseña incorrecta, revelación exitosa con contraseña válida y notificación de advertencia para tokens antiguos.

## [1.9.34] - 2026-09-24

### ⏱️ Prevención de Falso Timeout en Sincronización desde Producción

- **Panel Filament (`app/Filament/Pages/ProductionSyncProgress.php`)**:
  - En `checkTimeout()`, se detiene la validación de timeout por tiempo total si la importación ya inició (`processed > 0` o `total_steps > 0`), permitiendo importar grandes volúmenes de datos sin interrupción indebida.
- **Pruebas Automatizadas (`tests/Feature/Filament/ProductionSyncProgressTimeoutTest.php`)**:
  - Actualizados tests para validar que no ocurre timeout una vez comenzada la importación, manteniendo detección de timeout solo cuando el proceso encolado nunca arranca y excede el límite.

## [1.9.33] - 2026-09-23

### 🏷️ Desacople de Descuentos respecto a Evento Sale y Alineación con Pasarelas de Pago

- **Configuración de Sitio (`app/Models/SiteSetting.php`)**:
  - `forFrontend()` expone `price_source` y `price_percentage_source` en la raíz del payload público (`/api/v1/site-config`), asegurando disponibilidad directa para el frontend sin requerir token bearer.
- **Backend y API (`app/Models/Plant.php`, `EnrichesPlantPayload.php`, `PlantController.php`)**:
  - `Plant::resolveFinalPrice()` y `EnrichesPlantPayload::resolveApiDiscountPercentage()` resuelven el porcentaje de descuento exclusivamente a partir de la configuración `extra_settings.price_percentage_source` (`max_unit` -> `descuento_maximo_unidad`, `web_discount` -> `descuento_defecto_cotizacion_web`), independientemente de si `evento_sale` está activo o inactivo.
  - En `PlantController::index()`, el ordenamiento SQL (`orderByDiscountExpression`) ordena utilizando la fuente configurada en `price_percentage_source` en lugar del booleano `evento_sale`.
- **Frontend React (`frontend/src/pages/Home.jsx`)**:
  - `mapPlant` calcula el porcentaje de descuento aplicando la fuente definida en `config.price_percentage_source`.
  - `evento_sale` cumple su rol visual: conmuta la etiqueta de precio a `"Precio Sale: "` cuando está activo y `"Precio Final: "` cuando está inactivo (o `"Precio Base: "` si `price_source === 'base'`).
- **Pruebas Automatizadas**:
  - Actualizadas pruebas en `PlantTest.php` y `PlantApiFiltersTest.php` verificando la independencia del cálculo del precio final respecto a `evento_sale`.

## [1.9.32] - 2026-09-23

### 🔒 SiteSettings: Preservación de Claves no Expuestas en extra_settings

- **Panel Filament (`app/Filament/Pages/SiteSettings.php`)**:
  - En `save()`, incorporada fusión `array_merge` entre las configuraciones existentes de `extra_settings` y los datos del formulario, previniendo la sobreescritura accidental de metadatos o tokens no expuestos en componentes (ej. `salesforce_oauth`).

## [1.9.31] - 2026-09-23

### ⚙️ SiteSettings: Eliminación de Configuración 'Descuento expuesto en API'

- **Panel Filament (`app/Filament/Pages/SiteSettings.php`)**:
  - Eliminada la sección `'Descuento expuesto en API'` (`extra_settings.salesforce_discount_source`), dado que los descuentos quedaron centralizados de forma unificada a nivel de proyecto (Sale ON: `descuento_maximo_unidad`, Sale OFF: `descuento_defecto_cotizacion_web`).
- **Pruebas (`tests/Feature/SiteSettingsSalesforceSyncConfigTest.php`)**:
  - Removido test `test_it_persists_salesforce_discount_source_in_extra_settings` correspondiente al ajuste deprecado.

## [1.9.30] - 2026-09-23

### 🏢 Desacople de Descuentos de Planta y Centralización en Proyecto

- **Lógica de Descuentos (Sale On / Sale Off)**:
  - Centralizado el cálculo de descuento exclusivamente a nivel de proyecto:
    - **Sale ON** (`evento_sale = true`): Aplica `proyecto.descuento_maximo_unidad`. Si es 0 o nulo, no se aplica descuento (0%).
    - **Sale OFF** (`evento_sale = false`): Aplica `proyecto.descuento_defecto_cotizacion_web`. Si es 0 o nulo, no se aplica descuento (0%).
  - Desacoplado por completo `porcentaje_maximo_unidad` de la planta en el cálculo de precios.
- **Frontend React (`frontend/src/pages/Home.jsx`)**:
  - `mapPlant` calcula `porcentajeAplicado` leyendo `plant.proyecto?.descuento_maximo_unidad` durante Sale y `plant.proyecto?.descuento_defecto_cotizacion_web` fuera de Sale.
- **Backend API (`app/Http/Controllers/Api/Concerns/EnrichesPlantPayload.php`, `app/Http/Controllers/Api/PlantController.php`)**:
  - `resolveApiDiscountPercentage` y `resolveFinalPrice` en modelo `Plant` leen exclusivamente los descuentos de `proyecto`.
  - `projectPayload` incluye `descuento_maximo_unidad`, `descuento_defecto_cotizacion_web` y `descuento_iva`.
  - Ordenamiento SQL por precio con descuento (`orderByDiscountExpression`) consulta `descuento_maximo_unidad` de la tabla `proyectos` durante Sale.
- **Panel Filament (`PlantForm.php`, `PlantsTable.php`, `ProyectoForm.php`, `ProyectosTable.php`)**:
  - Agregado tooltip en `PlantsTable` en `% Máx. Unidad` y `% Desc. Web` avisando que los porcentajes vienen configurados desde el proyecto.
  - Configurado valor por defecto en `0` para `descuento_iva` (`Dcto. IVA`) tanto en el formulario de proyecto como a nivel de base de datos.
  - Eliminado input `porcentaje_maximo_unidad` del formulario de Plantas.
  - Reemplazada columna en tabla de plantas para reflejar `proyecto.descuento_maximo_unidad`.
  - Eliminado el fallback que precargaba `$record->plantas()->max('porcentaje_maximo_unidad')` en `descuento_maximo_unidad` del proyecto, quedando 100% manual e independiente.
- **Pruebas y Build**:
  - Suite de pruebas unitarias y de integración actualizada pasando al 100% (63 tests verificados). Compilación de activos finalizada con éxito.

## [1.9.29] - 2026-09-23

### 🏷️ Proyectos: Descuento IVA y Habilitación de Descuentos en Reserva Exigida

- **Panel Filament (`app/Filament/Resources/Proyectos/Schemas/ProyectoForm.php`)**:
  - Incorporado campo `descuento_iva` (`Dcto. IVA`) en la card *Reserva Exigida*, numérico con 2 decimales (`step(0.01)`) y sufijo `%`.
  - Habilitados los campos de descuento de la card: `descuento_defecto_cotizacion_web` y `descuento_maximo_unidad` quedan editables (`disabled` removido) y `dehydrated(false)` retirado para permitir persistencia manual.
- **Tabla de Proyectos (`app/Filament/Resources/Proyectos/Tables/ProyectosTable.php`)**:
  - Añadida columna `descuento_iva` con badge color morado (`purple`) y formato porcentual con 2 decimales, configurable como toggleable.
- **Base de Datos y Modelo (`app/Models/Proyecto.php`, `database/migrations/2026_09_23_180000_add_descuento_iva_to_proyectos_table.php`)**:
  - Creada migración agregando columna `descuento_iva` (`decimal(8,2)`, nullable) a la tabla `proyectos`.
  - Registrado `descuento_iva` en `$fillable`, `$casts` (`decimal:2`) y `syncableFields()`.
- **API y Sincronización (`app/Http/Controllers/Api/ProyectoController.php`, `app/Filament/Actions/SyncProjectsAction.php`)**:
  - Añadido `descuento_iva` a `$allowedFields` en `ProyectoController`.
  - Registrado en campos actualizables y sincronizables de `SyncProjectsAction`.
- **Pruebas Automatizadas (`tests/Unit/Models/ProyectoTest.php`, `tests/Feature/ProyectoResourceTest.php`)**:
  - Incorporadas aserciones de fillable, casteo decimal y configuración de componentes en el formulario de Filament.

## [1.9.28] - 2026-09-23

### 📖 Actualización Integral de Documentación (*.md)

- **Documentación General (`README.md`)**:
  - Actualizada versión del proyecto a `1.9.28` con fecha `2026-09-23`.
  - Incorporada documentación de **Sincronización desde Producción (Production Sync)**: exportación API, servicio `ProductionSyncService`, modal interactivo en Filament (`SyncFromProductionAction`), pantalla de seguimiento en vivo con detección de timeout, comando `php artisan production:sync` y soporte para sincronización de asesores con vinculación a proyectos y plantas.
  - Documentado **Orden Natural en Tablas de Plantas**: ordenación numérica en columnas `name`, `piso`, precios (`precio_base`, `precio_lista`, `precio_final`) y porcentajes de descuento.
  - Documentada acción de **Reseteo Masivo de Unidades Sale**: botón de cabecera `ResetSalePlantsAction` en el listado de plantas de Filament.
  - Actualizados comandos del **Scheduler Operativo** según `routes/console.php` (`reservations:expire` cada min, `SyncPlantsJob` condicionado cada min, `salesforce:refresh-token` cada 45 min, `model:prune` de preview links diario).
  - Corregidos comandos Artisan en la guía operativa (`sync:plants`, `production:sync`, `salesforce:refresh-token`).
  - Documentada la integración de **Protocolos de Descubrimiento para Agentes IA**: endpoints `/.well-known/acp.json`, `/.well-known/ucp`, `/openapi.json`, `/auth.md`, `/.well-known/oauth-authorization-server`, `/.well-known/mcp/server-card.json`, `/.well-known/agent-skills/` y `llms.txt`.
- **Directivas y Guía de Agentes (`AGENTS.md`)**:
  - Sincronizada versión documentada a `1.9.28` (`2026-09-23`).
  - Incorporado módulo activo `Production Sync` y servicio `ProductionSync/` en la arquitectura de directorios.
  - Documentadas adiciones recientes de orden natural, reseteo de unidades sale y protocolos de agentes.
- **Guía de Uso de la API (`API_USAGE.md`)**:
  - Detallado el payload completo de exportación en `GET /api/v1/production-sync/export` incluyendo asesores y relaciones pivote.
  - Añadido ejemplo cURL para exportación de snapshot de producción.
- **Sistema de Pagos (`PAYMENTS.md`)**:
  - Actualizada lista de verificación y notas operativas reflejando tests automatizados completos, emails con FinMail, acciones de aprobación en Filament y sanitización de metadata con `FlowLogMatrix`.


### 👥 Sincronización de Asesores desde Producción y Vinculación con Proyectos y Plantas

- **Exportación de Producción (`app/Http/Controllers/Api/ProductionSyncController.php`, `app/Models/Asesor.php`)**:
  - Incorporada colección `advisors` en el payload de exportación (`/api/v1/production-sync/export`), incluyendo `salesforce_id`, nombres, email, WhatsApp, avatar, estado activo y sus `proyectos_salesforce_ids` asociados.
  - Añadido `asesor_salesforce_id` en el `syncPayload()` de `Plant` para permitir la posterior vinculación de asesores a plantas.
- **Servicio de Sincronización (`app/Services/ProductionSync/ProductionSyncService.php`)**:
  - Incorporada descarga e importación de asesores mediante `syncAdvisor()` con resolución inteligente por `salesforce_id` (o fallback por `email`).
  - Sincronización automática de relaciones en tabla pivote `asesor_proyecto` mapeando los `proyectos_salesforce_ids`.
  - Vinculación automática en `syncPlant()` del `asesor_id` correspondiente a partir del `asesor_salesforce_id`.
  - Orden de sincronización estructurado: `site_settings` -> `projects` -> `advisors` -> `plants`.
- **Trabajo y Comandos (`app/Jobs/RunProductionSyncJob.php`, `app/Console/Commands/SyncFromProductionCommand.php`)**:
  - Actualizado conteo de pasos totales y mensajes de log final para informar asesores creados y actualizados.
  - Salida de consola Artisan en `production:sync` incluye desglose de asesores recibidos y procesados.
- **Pruebas Automatizadas (`tests/Feature/Api/ProductionSyncExportApiTest.php`, `tests/Unit/Services/ProductionSyncServiceTest.php`)**:
  - Pruebas unitarias y de integración actualizadas verificando la exportación, creación, actualización y asociación bidireccional de asesores y plantas. Suite completa pasando (458 tests, 1907 aserciones).

## [1.9.26] - 2026-09-23

### 🔢 Orden Natural en Tabla de Plantas (Nombre, Precios, Porcentajes, Piso)

- **Panel Filament (`app/Filament/Resources/Plants/Tables/PlantsTable.php`)**:
  - Implementado orden natural para la columna `name` (`Nombre`): ordenación numérica real (`21, 202, 1001, 1003`) evitando el orden lexicográfico de texto (`1001, 1003, 202`) tanto en orden ASC como DESC.
  - Implementado orden natural para la columna `piso` (`Piso`) considerando números de piso como enteros.
  - Corregido orden numérico en `precio_base`, `precio_lista` y `precio_final`: en ASC los registros sin precio se ubican al final sin estorbar las unidades con precio, y en DESC los registros con precio máximo aparecen primero.
  - Implementado orden numérico para `porcentaje_maximo_unidad` y `proyecto.descuento_defecto_cotizacion_web`.
- **Relación de Plantas en Proyecto (`app/Filament/Resources/Proyectos/RelationManagers/PlantasRelationManager.php`)**:
  - Añadido orden natural numérico para `name`, `piso` y `precio_lista`.
- **Sincronización desde Producción y Soporte para Entornos Locales**:
  - `EnsureTokenOriginIsAuthorized`: normalización y compatibilidad flexible para orígenes loopback (`127.0.0.1` y `localhost` con cualquier puerto o ruta como `/admin`), permitiendo que tokens generados en producción con URLs locales como `http://127.0.0.1:8000/admin` o `http://localhost:8000` autentiquen sin error 403.
  - `ProductionSyncService`: soporte para recibir parámetros opcionales (`$baseUrl`, `$token`, `$authorizedUrl`) en `fetchSnapshot()` y fallback inteligente a `config('app.url')` o `http://127.0.0.1:8000`.
  - `RunProductionSyncJob`: inicialización segura de propiedades con valores por defecto para evitar errores de des-serialización en colas.
  - `SyncFromProductionAction`: nuevo modal interactivo que permite ingresar o pre-llenar URL de producción, Token Bearer Sanctum, URL autorizada de origen (`http://127.0.0.1:8000/admin`), y opción `run_in_background` (por defecto apagada para ejecutar de inmediato de forma sincrónica sin requerir worker `queue:work` activo).
  - `ProductionSyncProgress`: detección de timeout (`checkTimeout`) cuando la sincronización excede el tiempo límite configurado (`services.production_sync.timeout`, por defecto 120s), registrando el mensaje de error fatal directamente en el *Log en vivo* y cambiando el estado a fallido.
  - `ActivityLogResource`: eliminada alerta falsa de log de error (`Accion erronea se esperaba prune y llego export`) al cargar acciones de cabecera en el panel.
  - `ListPlants`: añadido botón de cabecera `"Sincronizar desde producción"` para importar datos directamente desde el listado de plantas.
  - `SyncFromProductionCommand`: nuevo comando Artisan `php artisan production:sync` con soporte para opciones `--base-url=`, `--token=` y `--authorized-url=`.
- **Pruebas Automatizadas (`tests/Feature/Filament/PlantsTableNaturalSortingTest.php`, `tests/Feature/Filament/ProductionSyncProgressTimeoutTest.php`)**:
  - Nuevas pruebas feature verificando el orden natural ASC y DESC de nombres, precios y porcentajes, y el manejo de timeout en la pantalla de progreso.

## [1.9.25] - 2026-09-22

### 🔄 Acción de Reseteo de Unidades Sale en Plantas

- **Panel Filament (`app/Filament/Actions/ResetSalePlantsAction.php`)**:
  - Nueva acción `ResetSalePlantsAction` que desmarca masivamente todas las plantas asignadas como `unidad_sale = true` estableciéndolas en `false`.
  - Cuadro modal de confirmación (`¿Estás seguro de que deseas quitar todas las unidades Sale?...`) con advertencia clara y feedback de cuántas plantas fueron reseteadas.
  - Permite reiniciar la selección de unidades sale para reasignar nuevas plantas desde la tabla o mediante acciones masivas.
- **Página de Listado de Plantas (`app/Filament/Resources/Plants/Pages/ListPlants.php`)**:
  - Incorporado botón de cabecera `"Resetear plantas Sale"` accesible directamente en el panel administrativo de Plantas.
- **Pruebas Automatizadas**:
  - Nuevas pruebas de integración en `ResetSalePlantsActionTest` validando reseteo exitoso, manejo de estado vacío y registro de la acción en la cabecera del recurso. Total suite: 453 tests pasando.

### 🎯 Sobreescritura Selectiva de UTM Campaign por Canal de Contacto en Evento Sale

- **Panel Filament (`app/Filament/Pages/SiteSettings.php`)**:
  - Nuevo selector múltiple `extra_settings.sale_utm_campaign_channels` ("Canales a sobreescribir en Evento Sale") en la pestaña `SEO`, permitiendo elegir 1 o más canales de contacto activos.
  - Visualización del canal por defecto con sufijo `(Por defecto)`.
  - Valor por defecto: canal por defecto (`ContactChannel::getDefault()`).
  - Deshabilitado reactivamente cuando `evento_sale` está inactivo.
- **Configuración y API (`app/Models/SiteSetting.php`)**:
  - `SiteSetting::forFrontend()` expone `sale_utm_campaign_channels` (IDs) y `sale_utm_campaign_channel_slugs` (slugs) en la sección `seo`.
- **Mapeo de Leads Salesforce (`app/Services/Salesforce/SalesforceCaseMapper.php`)**:
  - Método `shouldOverrideCampaignForSale()` que verifica si el canal del submission (`contact_channel_id`, relación o slug/dominio) pertenece a los canales habilitados para sobreescritura de Sale.
  - Si el canal no está en la lista de canales permitidos, **NO sobreescribe** `utm_campaign`, preservando la campaña que envió el cliente o canal externo.
  - Si no hay canales configurados o la lista está vacía, no sobreescribe ningún canal.
- **Frontend (`frontend/src/contexts/SiteConfigContext.jsx`)**:
  - `saleCampaignOverride` en sesión solo se aplica si el slug del canal actual (`?channel=` o fallback `'sale'`) se encuentra en `sale_utm_campaign_channel_slugs`.
- **Pruebas Automatizadas**:
  - Nuevas pruebas en `SalesforceCaseMapperTest` verificando: sobreescritura solo para canales seleccionados, no sobreescritura para canales no seleccionados, comportamiento ante lista vacía, y no sobreescritura cuando evento sale está inactivo. Total suite: 450 tests pasando.

### 🏷️ Control Dinámico de UTM Campaign para Evento Sale y SEO

- **Panel Filament (`app/Filament/Pages/SiteSettings.php`)**:
  - Nuevo campo `extra_settings.sale_utm_campaign` ("UTM Campaign Evento Sale") ubicado inmediatamente debajo de `extra_settings.utm_campaign_default` en la pestaña `SEO`.
  - Deshabilitado reactivamente cuando `evento_sale` es `false`, habilitándose únicamente cuando `evento_sale` está activo.
- **Configuración y API (`app/Models/SiteSetting.php`)**:
  - `SiteSetting::forFrontend()` expone `sale_utm_campaign`, `sale_campaign_override` y `sale_event.utm_campaign`.
  - Resolución inteligente de campaña para evento sale con fallback en cascada: `sale_utm_campaign` -> `sale_event_name` -> `utm_campaign_default`.
- **Mapeo de Leads Salesforce (`app/Services/Salesforce/SalesforceCaseMapper.php`)**:
  - Cuando `evento_sale === true`: sobreescribe `utm_campaign` con la campaña del evento sale en curso (ej: `CyberMonday`).
  - Cuando `evento_sale === false`: comportamiento normal sin sobreescritura forzada, preservando los UTM de la URL o el formulario entrante y aplicando el fallback configurado (`utm_campaign_default`) solo ante la ausencia de parámetros.
- **Frontend y Gestión de Sesión UTM (`frontend/src/utils/utmSession.js` & `SiteConfigContext.jsx`)**:
  - Se eliminó el reemplazo incondicional hardcodeado de `utm_campaign`.
  - `setUtmDefaultOverrides` ahora recibe opciones `{ isSaleEvent, saleCampaignOverride }` forzando la sobreescritura de campaña en sesión únicamente cuando el evento sale está activo.
- **Pruebas Automatizadas**:
  - Nuevos tests unitarios en `SalesforceCaseMapperTest` validando sobreescritura estricta con `sale_utm_campaign` en modo sale y preservación intacta de campañas normales cuando sale está desactivado.
  - Verificación de payload en `SiteSettingFrontendConfigTest`.

## [1.9.22] - 2026-09-22

### 📖 Actualización Exhaustiva de Documentación de API (`API_USAGE.md`)

- **Corrección de Niveles de Autorización**:
  - Reclasificados endpoints de reservas (`POST /api/v1/reservations`, `GET /api/v1/reservations/planta/{plantId}`, `DELETE /api/v1/reservations/{sessionToken}`), pasarelas (`GET /api/v1/payment-gateways`) y checkout anónimo (`POST /api/v1/checkout`) como protegidos únicamente por `token.origin` (no requieren `auth:sanctum`).
  - Documentado alias en inglés `GET /api/v1/reservations/plant/{plantId}`.
  - Documentado bypass de previsualización para `FrontendPreviewLink` (staging y preview de Filament) en el middleware `token.origin`.
- **Protocolos de Descubrimiento y Agentes**:
  - Incorporada documentación de especificación OpenAPI 3.1.0 con Machine Payment Protocol (`/openapi.json`), RFC 9727 (`/.well-known/api-catalog`), ACP 1.0 (`/.well-known/acp.json`), UCP 1.0 (`/.well-known/ucp`), MCP SEP-1649 (`/.well-known/mcp/server-card.json`), ARD 1.0 (`/.well-known/ai-catalog.json`), Agent Skills (`/.well-known/agent-skills/index.json`) y Auth.md (`/auth.md`).
- **Seguridad en Configuración de Sitio (`/api/v1/site-config`)**:
  - Detallado comportamiento de enmascaramiento seguro de credenciales de pasarelas de pago (`gateway_transbank_config`, `gateway_mercadopago_config`, `gateway_manual_config`), `price_source` y `price_percentage_source` ante peticiones públicas no autenticadas.
- **Catálogo, Filtros y Rutas Semánticas de Plantas**:
  - Documentada la ruta semántica `GET /api/v1/plantas/proyecto/{projectSlug}/unidad/{unitName}`.
  - Documentado endpoint de catálogo de filtros `GET /api/v1/plantas/filtros-ubicacion` y estructura de respuesta.
  - Detallados todos los filtros disponibles (`is_active`, `evento_sale`, `disponible`, `catalog_slug`, `comuna_slug`, `programa`, `programa2`, etc.) y reglas de cálculo de `precio_final` en modo normal vs sale.
- **Proyectos y Campos Computados**:
  - Documentados campos `precio_desde`, `tipologias`, `descuento_defecto_cotizacion_web`, `descuento_maximo_unidad` y parámetros `include_asesores`, `include_plantas` y `evento_sale`.

## [1.9.21] - 2026-09-21

### 🔍 SEO Internacional (Hreflang) y SERP Snippet Preview en Panel Filament

- **Hreflang Multi-región en Frontend**:
  - `frontend/index.html`: Enlaces alternativos estáticos `<link rel="alternate" hreflang="es-CL">` y `<link rel="alternate" hreflang="x-default">`.
  - `frontend/src/services/siteConfig.js`: Métodos `setHreflang()` y `setAlternateLink()` para sincronizar enlaces hreflang dinámicamente con la URL canónica y el locale de configuración (`es-CL`, `es-419`, `es-ES`).
  - `frontend/scripts/prerender-routes.mjs`: Inyección automática de directivas `hreflang="es-CL"` y `hreflang="x-default"` en todas las páginas prerenderizadas (`/`, `/plantas`, `/f`).
  - `frontend/scripts/validate-seo.mjs`: Verificación automatizada de consistencia hreflang durante el build.
- **SERP Snippet Preview en Panel de Configuración**:
  - `resources/views/filament/components/serp-snippet-preview.blade.php`: Nuevo componente Blade interactivo que simula en tiempo real la apariencia del resultado en Google Search (título en azul, URL miga de pan con favicon, meta descripción).
  - Selector de vista **Desktop** vs **Mobile** mediante Alpine.js con límites recomendados de caracteres (50-60 para títulos, 120-160 para descripciones) e indicadores visuales de optimización.
  - `app/Filament/Pages/SiteSettings.php`: Nueva sección "Vista Previa en Google (SERP Snippet Preview)" en la pestaña `SEO`, enlazada reactivamente con `default_meta_title`, `default_og_description`, `site_name` y `site_locale`.

## [1.9.20] - 2026-09-21

### 🤖 Protocolos de Descubrimiento IA y Compatibilidad con Agentes (Agent Readiness)

- **Negociación de Contenido Markdown (`Accept: text/markdown`)**:
  - `NegotiateMarkdownForAgents.php`: Resolvió código 403 sirviendo directamente representación en Markdown con cabeceras `Content-Type: text/markdown; charset=UTF-8`, `Vary: Accept` y `x-markdown-tokens` para solicitudes públicas.
  - `frontend/public/.htaccess`: Incorporado permiso explícito `Require all granted` / `Allow from all` para extensiones `.md`, `.txt`, `.json` y `.xml` evitando bloqueos del servidor web Apache, y regla de reescritura para negociar `index.md`.
  - `frontend/public/index.md` y `MarkdownRepresentationService.php`: Catálogo enriquecido con todos los enlaces de descubrimiento para agentes y desarrolladores.
- **Autenticación y Registro de Agentes (`Auth.md`)**:
  - `frontend/public/auth.md` y ruta `GET /auth.md` en Laravel: Encabezado obligatorio `# Auth.md - iLeben Agent Authentication and Registration`, especificación de provisión de agentes (`POST /agent/register`), tipos de identidad (`anonymous`, `identity_assertion`), tipos de credenciales (`bearer_token`, `api_key`), scopes y endpoints de reclamo/revocación.
- **Descubrimiento OAuth 2.0 y OpenID Connect**:
  - `/.well-known/oauth-authorization-server`: Metadatos de servidor de autorización (RFC 8414) con bloque `agent_auth` para registro automatizado de agentes.
  - `/.well-known/openid-configuration`: Metadatos estándar OIDC para autenticación federada.
- **Metadatos de Recursos Protegidos OAuth (RFC 9728)**:
  - `/.well-known/oauth-protected-resource`: Documento JSON con `resource`, `authorization_servers`, `scopes_supported` y `bearer_methods_supported: ["header"]`.
- **Tarjeta de Servidor MCP (SEP-1649)**:
  - `/.well-known/mcp/server-card.json`: Especificación para servidores Model Context Protocol con transporte Streamable HTTP (`/mcp`) y declaración de capacidades (herramientas, recursos, prompts).
- **Índice de Descubrimiento de Habilidades de Agentes (Agent Skills RFC v0.2.0)**:
  - `/.well-known/agent-skills/index.json`: Índice de habilidades con esquema `$schema: https://schemas.agentskills.io/discovery/0.2.0/schema.json` y hashes SHA-256 criptográficos verificados.
  - Habilidades publicadas: `catalog-search/SKILL.md`, `unit-reservation/SKILL.md`, `auth-md/SKILL.md`.
- **Integración WebMCP en Navegador**:
  - `frontend/src/services/webMcp.js`: Implementación de la API WebMCP de W3C / Chrome EPP. Registra las herramientas `search_projects`, `get_plant_details`, `contact_sales_advisor` y `reserve_unit` mediante `navigator.modelContext.provideContext()` y `navigator.modelContext.registerTool()`, con shim reactivo para detección inmediata en escaneos pasivos.
  - `frontend/src/main.jsx`: Inicialización automática durante la carga de página.
- **Manifiesto ARD (Agentic Resource Discovery)**:
  - `/.well-known/ai-catalog.json`: Manifiesto con formato `specVersion: "1.0"`, identificador DID Web, entradas URN AIR (`urn:air:<domain>:...`) y consultas semánticas representativas (`representativeQueries`).
  - `robots.txt`: Incorporada directiva `Agentmap: https://sale.ileben.cl/.well-known/ai-catalog.json`.
  - `frontend/index.html`: Enlaces `<link rel="ai-catalog">` y `<link rel="mcp-server-card">` en cabecera HTML.
- **Tests Automatizados**:
  - `tests/Feature/Api/AgentReadinessDiscoveryTest.php`: 10 nuevos tests de características validando estructura, encabezados CORS y payloads de todos los protocolos (446 tests en total pasando).

## [1.9.19] - 2026-09-21

### ✅ Scripts de Conversión y Píxel Post-Formularios (Contacto y Pago) — Estilo mow-plugin

- **Backend (Filament & Model)**:
  - `SiteSettings.php`: Nueva sección "Scripts de Conversión / Píxel (Formularios y Pagos)" dentro de la pestaña `Personalización`.
    - Campos: `extra_settings.conversion_scripts_enabled` (toggle general), `extra_settings.conversion_scripts_debug` (logs en consola del navegador), `extra_settings.post_contact_script` (textarea script tras enviar contacto), `extra_settings.post_payment_script` (textarea script tras pagar o iniciar reserva).
  - `SiteSetting.php`: `forFrontend()` expone el objeto `conversion_scripts` con `enabled`, `debug`, `post_contact_script` y `post_payment_script`.
- **Frontend (React / Vite)**:
  - `utils/conversionTracker.js` (nuevo): Motor multiformato basado en la arquitectura de `scooller/mow-plugin`:
    - Ejecuta bloques `<script>` creando elementos DOM reales en `<head>`/`<body>`.
    - Extrae `<img>` de bloques `<noscript>` y dispara peticiones GET invisibles (beacons 1x1).
    - Soporta tags directos `<img>` y URLs limpias.
    - Reemplazo dinámico de variables (`{form_id}`, `{name}`, `{email}`, `{phone}`, `{amount}`, `{order_id}`, `{gateway}`, `{unit_id}`, etc.).
    - Dispara `CustomEvent('pixel_tracker_dispatched')` en `window` para observabilidad.
  - `Contact.jsx`: Dispara `triggerContactConversion` tras enviar formulario de contacto con éxito.
  - `Home.jsx`: Dispara `triggerPaymentConversion` tras iniciar checkout o enviar comprobante manual.
  - `Payment.jsx`: Dispara `triggerPaymentConversion` idempotentemente tras confirmar transacción aprobada.
- **Tests Automatizados**:
  - `SiteSettingFrontendConfigTest.php`: Nueva prueba `test_for_frontend_includes_conversion_scripts()` verificando persistencia y entrega en `/api/v1/site-config`. Total suite: 436 tests pasando.

## [1.9.18] - 2026-09-21

### ✅ SEO Estructurado — Evento Sale/Cyber (controlado desde backend)

**Backend:**
- `SiteSettings.php` (Filament): nueva Section "Evento Sale — SEO" en el tab SEO, visible solo cuando `evento_sale` está activo. Campos: `sale_event_name`, `sale_event_description`, `sale_event_start_date`, `sale_event_end_date`, `sale_og_image_id` (CuratorPicker).
- `SiteSetting.php` (Model): se agrega `DatePicker` al import de Filament; `forFrontend()` expone `seo.sale_event` (null cuando inactivo, objeto con 5 campos cuando activo); `seo.og_image` ahora prefiere `sale_og_image` durante el evento.

**Frontend:**
- `utils/saleEventSchema.js` (nuevo): builders `buildSpecialAnnouncementSchema` y `buildSaleEventSchema` que generan JSON-LD schema.org desde los datos del backend.
- `App.jsx`: nuevo `useEffect` que inyecta/elimina los dos schemas (`ileben-jsonld-sale-announcement`, `ileben-jsonld-sale-event`) reactivamente cuando `config.seo.sale_event` cambia. Limpieza automática en unmount.

**Sin cambios en el comportamiento** cuando `evento_sale` está desactivado (sale_event === null → schemas no se inyectan).

## [1.9.17] - 2026-09-21

### ✅ Agent Commerce Protocol Discovery (ACP, UCP, MPP)

- **ACP** (`/.well-known/acp.json`): Agentic Commerce Protocol discovery document. Declares `protocol.name: "acp"`, `api_base_url`, `transports: ["http"]`, and `capabilities.services` (real-estate-catalog, property-listings, contact-submissions, reservations, payments).
- **UCP** (`/.well-known/ucp`): Universal Commerce Protocol discovery document. Includes `protocol_version`, `services` (catalog, reservations, checkout), `capabilities` (payment_methods: transbank, mercadopago, card; currencies: CLP), and `endpoints` map.
- **MPP** (`/openapi.json`): Full OpenAPI 3.1 document with `x-payment-info` extensions on payable operations (catalog, checkout). Maps Transbank / Mercado Pago to MPP `card` payment method. Includes top-level `x-service-info` with categories.
- **llms.txt** (`MarkdownRepresentationService`): Updated "Recursos para Agentes" section to advertise the three new discovery endpoints and the Transbank/Mercado Pago payment methods.
- x402 (crypto) **descartado** — no aplica ya que los métodos de pago son Transbank y Mercado Pago (no cripto).

## [1.9.16] - 2026-09-21

### 🚦 Preferencias de Uso de Contenido IA (`Content-Signal` en `robots.txt`)
- **Directivas Content Signals (`frontend/public/robots.txt`, `public/robots.txt`)**:
  - Incorporada la directiva estándar `Content-Signal: ai-train=no, search=yes, ai-input=no` bajo el bloque `User-agent: *` según el estándar de `contentsignals.org` y el borrador IETF.
  - Permite la indexación y búsqueda por motores IA (`search=yes`), mientras restringe el entrenamiento de modelos fundacionales (`ai-train=no`) y el uso como input de generación (`ai-input=no`).
- **Ruta Dinámica en Backend (`routes/web.php`)**:
  - Creado endpoint `GET /robots.txt` en Laravel para garantizar consistencia entre tests de aplicación y entornos de ejecución web.
- **Validación Automática de Build (`validate-seo.mjs`)**:
  - Incorporada aserción para verificar la presencia de `Content-Signal` en `dist/robots.txt` durante `npm run build`.
- **Tests Automatizados (`ContentSignalsRobotsTest.php`)**:
  - Nueva prueba unitaria verificando la entrega de `robots.txt` con `Content-Signal` y enlaces a sitemap. Total: 42 tests backend pasando sin fallos.

## [1.9.15] - 2026-09-21

### 📝 Negociación de Contenido Markdown para Agentes IA (`Accept: text/markdown`)
- **Middleware Global de Negociación (`NegotiateMarkdownForAgents.php`, `bootstrap/app.php`)**:
  - Implementado middleware global que detecta solicitudes de agentes con cabecera `Accept: text/markdown`.
  - Entrega una representación limpia en Markdown de la página y catálogo inmobiliario con cabecera `Content-Type: text/markdown; charset=UTF-8`, `Vary: Accept` y estimación de tokens `x-markdown-tokens`.
  - Mantiene intacta la respuesta HTML tradicional para navegadores y usuarios humanos.
- **Servicio Generador Markdown (`MarkdownRepresentationService.php`)**:
  - Generación dinámica de la estructura de proyectos activos, comunas, regiones, canales de contacto y enlaces directos a OpenAPI y API Catalog.
- **Rutas Estándar `llms.txt` (`routes/web.php`, `frontend/public/`)**:
  - Habilitados endpoints `GET /llms.txt` y `GET /.well-known/llms.txt` según la convención de `llmstxt.org`.
  - Creados archivos estáticos `frontend/public/llms.txt` y `frontend/public/index.md` distribuidos en el bundle de Vite.
- **Servidor Web Apache (`frontend/public/.htaccess`)**:
  - Incorporadas reglas de reescritura para servir `index.md` automáticamente ante solicitudes `Accept: text/markdown` en la raíz.
- **Tests Automatizados (`MarkdownContentNegotiationTest.php`)**:
  - Cobertura completa de negociación con cabecera `Accept`, endpoints `llms.txt` y persistencia de default HTML para navegadores. Total: 41 tests backend pasando.

## [1.9.14] - 2026-09-21

### 🤖 Descubrimiento de Agentes IA (RFC 8288 & RFC 9727)
- **Cabeceras HTTP `Link` (`AddAgentDiscoveryHeaders.php`, `bootstrap/app.php`)**:
  - Creado middleware `AddAgentDiscoveryHeaders` registrado en el grupo `web` para inyectar cabeceras de respuesta `Link` estándar RFC 8288:
    - `Link: </.well-known/api-catalog>; rel="api-catalog"`
    - `Link: </api/v1>; rel="service-desc"; type="application/json"`
    - `Link: </api/v1>; rel="service-doc"`
- **Catálogo de API RFC 9727 (`/.well-known/api-catalog`)**:
  - Endpoint en Laravel (`routes/web.php`) respondiendo a `GET` y `HEAD` con tipo de contenido `application/linkset+json` y estructura estándar `linkset` señalando a la especificación OpenAPI v1 (`/api/v1`).
  - Archivo físico estático en `frontend/public/.well-known/api-catalog` para compatibilidad con hosting estático o CDN.
- **Configuración Web Server (`.htaccess`)**:
  - Actualizado `frontend/public/.htaccess` y `public/.htaccess` con cabeceras `mod_headers` para respuestas `Link` y MIME type `application/linkset+json`.
- **Frontend DOM (`frontend/index.html`, `validate-seo.mjs`)**:
  - Incorporadas etiquetas `<link rel="api-catalog">`, `<link rel="service-desc">` y `<link rel="service-doc">` en el `<head>` de `index.html`.
  - Añadida validación automática en `validate-seo.mjs`.
- **Tests Automatizados (`AgentDiscoveryHeadersTest.php`)**:
  - Creada suite de pruebas unitarias verificando cabeceras `Link` en raíz web, respuesta JSON del catálogo y respuesta HTTPS `HEAD` requerida por RFC 9727. 36 tests pasando sin errores.

## [1.9.13] - 2026-09-21

### 🏗️ Arquitectura de Shell y Layout Web Awesome (`<wa-page>`)
- **Adopción de `<wa-page>` en Shell Principal (`App.jsx`, `App.scss`)**:
  - Implementado el componente `<wa-page>` como contenedor principal de la aplicación siguiendo la receta oficial de sitio de marketing de Web Awesome.
  - El shell ahora centraliza de forma persistente el `<SiteHeader />` (`slot="header"` y `slot="navigation"`) y `<SiteFooter />` (`slot="footer"`).
  - Eliminados los hooks duplicados, event listeners de redimensionamiento manual (`matchMedia`) y el drawer manual (`<wa-drawer>`), reemplazándolos por las capacidades integradas de `<wa-page>` (`data-toggle-nav` y `data-drawer="close"`).
  - Aplicada la regla de padding cero (`wa-page main { padding: 0 }`) para garantizar banners y secciones hero a sangre (full-bleed).
  - Incorporadas reglas de estilo con scope `view="desktop"` y `view="mobile"` para ocultar la barra lateral en escritorio y alternar la navegación móvil limpiamente.
- **Limpieza de Vistas Hijas (`Home.jsx`, `Contact.jsx`, `Payment.jsx`)**:
  - Eliminadas las instancias repetidas de `<SiteHeader />` y `<SiteFooter />` de todas las páginas y estados de carga/error intermedios, evitando parpadeos de montaje y desincronizaciones de UI.
- **Tests Automatizados (`ProyectoApiFiltersTest.php`)**:
  - Actualizada la aserción de campos por defecto en `ProyectoApiFiltersTest` para contemplar los campos computados `precio_desde` y `tipologias`.
  - Pasada con éxito la suite completa de 33 tests API y validación de prerenderizado/SEO en frontend.

## [1.9.12] - 2026-09-21

### 🚀 Optimización SEO Técnico, Marketing & Marcado Semántico LLMO
- **Schema.org Enriquecido (`App.jsx`, `Home.jsx`)**:
  - Incorporado tipo `RealEstateAgent` y dirección postal (`PostalAddress`) en la entidad principal `Organization`.
  - Evolucionado el marcado estructurado de plantas a `['Product', 'RealEstateListing']` incorporando el objeto `about` de tipo `Apartment` / `SingleFamilyResidence`, con dormitorios (`programa`), superficie m² (`QuantitativeValue`) y dirección postal completa (`streetAddress`, `addressLocality`, `addressRegion`, país `CL`).
- **SEO & Jerarquía Semántica (`Home.jsx`)**:
  - Activado y promovido el encabezado principal a `<h1>` semántico con texto contextual dinámico según proyecto seleccionado o catálogo general.
  - Sincronización reactiva de `og:image` de la unidad seleccionada en páginas de detalle de planta para vistas previas en redes sociales y mensajería.
- **Validación**: Compilación frontend exitosa (`npm run build`), prerenderizado de rutas completado y 27 tests de backend pasando sin errores.

### 🔄 Migración de Componentes y Atributos Web Awesome
- **Migración a sintaxis moderna de Web Awesome 3.x (`Home.jsx`, `Contact.jsx`, `PlantDetailDialog.jsx`)**:
  - Reemplazado atributo obsoleto `clearable` por el estándar oficial `with-clear` en elementos `<wa-select>`.
  - Reemplazado atributo obsoleto `variant="primary"` por `variant="brand"` en `<wa-button>` y `<wa-tag>`.
  - Removido `variant="default"` en `<wa-button>` reemplazándolo por el tratamiento por defecto del componente (`neutral`).
- **Servicio Web Awesome (`webAwesome.js`)**: Agregada la importación faltante del componente `<wa-spinner>` (`@web.awesome.me/webawesome-pro/dist/components/spinner/spinner.js`).

## [1.9.10] - 2026-09-21

### 📦 Dependencias Frontend
- **Actualización de Web Awesome Pro (`frontend/package.json`)**: Actualizado `@web.awesome.me/webawesome-pro` de `^3.7.0` a `^3.13.0`.
- **Configuración de Autenticación NPM (`frontend/.env`)**: Actualizado el token de autenticación para el registro privado de Cloudsmith (`WEBAWESOME_NPM_TOKEN`).
- **Build Frontend**: Compilación y prerenderizado validados sin errores con Vite y SEO check.

## [1.9.9] - 2026-09-21

### 🛠️ Correcciones y Flujo Anónimo de Checkout
- **Reservas y Checkout Anónimos (`routes/api.php`)**: Movidas las rutas de checkout (`POST /api/v1/checkout`) y reservas (`POST /api/v1/reservations`, `DELETE /api/v1/reservations/{sessionToken}`, `GET /api/v1/reservations/planta/{plantId}`) fuera del middleware `auth:sanctum` al grupo público con `token.origin`.
- **Servicio y Controlador de Reservas (`PlantReservationService.php`, `PlantReservationController.php`)**: Habilitado `$userId` nullable en la creación y extensión de reservas de plantas. Soporte para control y liberación anónima por `session_token` único (UUID).
- **Controlador de Checkout (`CheckoutController.php`, `CheckoutInitiateRequest.php`)**: Habilitada la autorización pública y resolución automática de clientes anónimos a través de `User::firstOrCreate(...)` vinculando su email de facturación.
- **Frontend Dialog & Home (`PaymentGatewayDialog.jsx`, `Home.jsx`)**: Eliminadas restricciones que impedían reservar una unidad o completar el proceso de checkout a usuarios no autenticados en el navegador.
- **Tests Automatizados**: Incorporados tests en `ApiAuthenticationTest` y validada la suite completa de `ManualCheckoutFlowTest`.

## [1.9.8] - 2026-09-21

### 🛠️ Correcciones
- **Ruta `payment-gateways` pública en API (`routes/api.php`)**: Movido el endpoint `GET /api/v1/payment-gateways` fuera del middleware `auth:sanctum` al grupo de catálogo con `token.origin`. Esto permite a los visitantes del frontend explorar y seleccionar pasarelas disponibles sin requerir inicio de sesión previo.
- **Soporte de Enlaces Preview (`EnsureTokenOriginIsAuthorized.php`)**: Permitido acceso a rutas protegidas por `token.origin` para enlaces preview autorizados (`FrontendPreviewLink::isAuthorizedForRequest()`) sin requerir un token Bearer estático.
- **Tests Automatizados**: Añadidos tests en `ApiAuthenticationTest` para verificar el acceso no autenticado a `payment-gateways` con token de API y con token preview.

## [1.9.7] - 2026-09-21

### 🔒 Parches de Seguridad
- **Path Traversal en Curator (`routes/web.php`)**: Implementada validación de `realpath` y confinamiento de directorio sobre `storage/app/public` en la ruta `/curator/{path}` para prevenir acceso arbitrario a archivos del sistema (e.g. `.env`).
- **Control de Acceso en Sync Export (`ProductionSyncController.php`)**: Requerida verificación administrativa (`isAdmin()`) en `GET /api/v1/production-sync/export` para impedir que usuarios registrados sin privilegios extraigan configuraciones sensibles de pasarelas de pago y datos del sistema.
- **Exención CSRF para Webhooks de Pago (`bootstrap/app.php`)**: Añadida la ruta `payments/*` a `validateCsrfTokens(except: ...)` para garantizar la recepción confiable de notificaciones POST externas de Mercado Pago y Transbank.
- **Tests Automatizados**: Añadido `CuratorRouteSecurityTest` y ampliado `ProductionSyncExportApiTest` para validar defensas.

## [1.9.6] - 2026-08-11

### ✨ Nuevas características

#### API de Proyectos — `precio_desde` y `tipologias`
- Agregados campos computados `precio_desde` y `tipologias` a los endpoints `GET /api/v1/proyectos` y `GET /api/v1/proyectos/{id}`.
- `precio_desde`: precio de lista (`precio_lista`) más bajo entre las plantas activas del proyecto.
- `tipologias`: agrupación de plantas activas por `programa` (dormitorios) + `programa2` (baños) + `tipo_producto`, con cantidad de unidades, precio mínimo, superficie útil min/max.
- Cálculo vía query `GROUP BY` optimizada (no carga plantas a memoria).
- Ambos campos aparecen por defecto en ambos endpoints y se omiten si el cliente usa `?fields=` sin incluirlos.
- Tests feature cubriendo listado, detalle, proyecto sin plantas, exclusión de plantas inactivas y selección de campos.

#### Salesforce OAuth — Auto-Refresh Proactivo
- Refactorización para implementar auto-refresh proactivo de tokens antes de que expiren silenciosamente.
- Creados los métodos `isTokenExpiringSoon()` y `proactiveRefresh()` en `SalesforceService` para manejar proactivamente la expiración.
- Implementado el wrapper centralizado `executeWithTokenProtection()` que valida el token, hace refresh proactivo o intenta reconexión y fallback de retry en caso de error. 
- Refactorizados `query()` y `createCase()` para usar el wrapper.
- Refactorizado el primer intento de `createLead()` para usar el wrapper, manteniendo la lógica de retry específica de payloads.
- Actualizado el comando `salesforce:refresh-token` para hacer un refresh proactivo cuando detecta que el token está próximo a expirar en lugar de solo resincronizar el backup.
- Modificado el scheduler en `routes/console.php` para ejecutar `salesforce:refresh-token` cada 45 minutos (en vez de cada 20 horas) para anticiparse a la expiración de ~2h de Salesforce.
- Protegido `SyncPlantsJob` aplicando `isTokenExpiringSoon()` y `proactiveRefresh()` antes de la acción principal, y usando `tryAutoReconnect()` en lugar de forzar redirección web (`Forrest::authenticate()`).
- Mejorada la UI de la sección "Conexión OAuth" en `SiteSettings` con Filament, incluyendo un badge visual (color verde/rojo) e información de la expiración estimada del token.
- Agregados nuevos tests (`SalesforceProactiveRefreshTest.php`) para las funciones de refresh proactivo.

---

## [1.9.5] - 2026-06-01

### 🔒 Seguridad y Observabilidad

#### Hardening de logs en flujos de pago
- Sanitización de tokens y reducción de payloads crudos en logs de Transbank y MercadoPago.
- Eliminación de persistencia de campos sensibles innecesarios en metadata de webhooks (`transbank_abort_payload`, `mercadopago_payment`).
- Ajustes de contexto para mantener trazabilidad operativa sin exponer PII/secrets.

#### Matriz centralizada de niveles de log
- Nuevo `App\Support\FlowLogMatrix` para definir severidad por evento/flujo.
- Adopción de la matriz en:
  - `PaymentWebhookController`
  - `CreateSalesforceCaseJob`
  - `ProductionSyncService`
  - `TransbankService`
  - `MercadoPagoService`
- Estandarización de niveles (`debug/info/warning/error/critical`) para reducir ruido y mejorar alertamiento.

### 🧱 Manejo de Errores

#### Excepciones con mejor trazabilidad
- Refactor de rethrows en servicios de pago para preservar excepción previa (`previous`) y mantener stack trace original.

#### Production Sync
- Logging explícito para:
  - Configuración incompleta
  - Respuestas HTTP no exitosas
  - Errores de red/excepciones al consumir snapshot remoto

### ✅ Tests

- Nuevas validaciones en `PaymentWebhookControllerTest` para asegurar sanitización de metadata de retorno cancelado en Transbank.
- Nuevos tests en `ProductionSyncServiceTest` para casos de logging en configuración faltante, HTTP no exitoso y excepción de conexión.
- Nuevo `FlowLogMatrixTest` para validar el mapeo de niveles por evento y fallback por defecto.

---

## [1.9.4] - 2026-06-01

### 🔄 Cambios

#### Salesforce OAuth — refinamientos de auto-reconexión y refresh
- `CreateSalesforceCaseJob` ahora intenta `tryAutoReconnect()` tanto cuando OAuth está marcado como desconectado como cuando no hay token en caché, antes de omitir el envío.
- `salesforce:refresh-token` dejó de forzar `Forrest::refresh()` cuando ya existe token en caché; en ese caso ahora sincroniza y persiste backups (`token_cache_backup` / `refresh_token_cache_backup`) para evitar `invalid_grant` en escenarios con rotación de refresh token.
- `SalesforceService::updateTokenBackup()` detecta refresh token rotado dentro del blob de token y lo sincroniza en caché + DB.
- El scheduler de `salesforce:refresh-token` quedó explícito como `cron('0 */20 * * *')` con `withoutOverlapping()` en `routes/console.php` (compatibilidad con versión instalada).

---

## [1.9.3] - 2026-05-29

### 🔄 Cambios

#### Salesforce OAuth — Auto-reconexión silenciosa tras pérdida de token
Resuelve la pérdida de sincronización que ocurría al limpiar el caché (deployments, restart de Redis).

**Problema raíz**: `omniphx/forrest` almacena el access token y el refresh token únicamente en el caché de Laravel (`forrest_token`, `forrest_refresh_token`). Al ejecutar `php artisan cache:clear`, ambas claves se eliminan. Sin el refresh token en caché, Forrest no puede renovar el access token y lanza `MissingResourceException`, lo que provocaba marcación manual como "desconectado" en el panel.

**Solución implementada**:
- `SalesforceOAuthController::callback()` persiste el backup cifrado de `forrest_token` y `forrest_refresh_token` en `SiteSetting.extra_settings.salesforce_oauth` (`token_cache_backup`, `refresh_token_cache_backup`) tras cada OAuth exitoso.
- `SalesforceService::tryAutoReconnect()`: restaura ambos tokens al caché desde la DB y llama `Forrest::refresh()` para obtener un nuevo access token de Salesforce sin intervención del usuario. Retorna `bool`.
- `SalesforceService::updateTokenBackup()`: actualiza `token_cache_backup` en DB con el nuevo access token tras cada refresh.
- `CreateSalesforceCaseJob`: reordenados los checks — `isSalesforceOAuthMarkedDisconnected()` primero (fast path), luego `!Forrest::hasToken()` con llamada a `tryAutoReconnect()`.
- Nuevo comando `salesforce:refresh-token` (artisan): renueva el token proactivamente; si el token está en caché realiza refresh directo, si no intenta auto-reconexión desde backup en DB.
- Schedule: `salesforce:refresh-token` corre cada 20 horas via `routes/console.php` con `withoutOverlapping()`.

**Requisito post-deploy**: reconectar OAuth una vez desde `/admin/site-settings` para generar el backup inicial.

#### Panel — Proyectos inactivos visibles en SiteSettings
- El selector de proyecto en `SiteSettings` ahora incluye proyectos inactivos con prefijo `[Inactivo]` en lugar de filtrarlos.
- Ordenamiento actualizado: activos primero (`orderBy('is_active', 'desc')`), luego por nombre y comuna.

---

## [1.9.2] - 2026-05-27

### 🔒 Seguridad

#### Endurecimiento de autenticación API (`token.origin`)
- `EnsureTokenOriginIsAuthorized` ahora **rechaza requests sin bearer token** con 401 (antes los dejaba pasar).
- Tokens inválidos o inexistentes en BD también retornan 401 en lugar de pasar silenciosamente.
- Se corrigió el bug donde `$request->user()` era `null` en rutas sin `auth:sanctum`: el middleware ahora usa `PersonalAccessToken::findToken()` directamente, sin depender de sesión de usuario.
- Import corregido a `App\Models\PersonalAccessToken` (modelo personalizado con `authorized_url` y scope `active`).
- Verificación explícita de expiración de token (`expires_at`).

#### Ocultamiento de datos sensibles en `/api/v1/site-config`
- Los campos `payment_gateways.*.config`, `price_source` y `price_percentage_source` **ya no se exponen públicamente**.
- Se muestran únicamente si el request incluye un bearer token válido y activo.
- `SiteSetting::forFrontend()` recibe la variable `$hasValidToken` derivada del bearer token del request.

---

## [1.9.1] - 2026-05-27

### 🔄 Cambios

#### Salesforce OAuth — Duración y Reconexión de Token
- Se incorporó configuración explícita de OAuth scope y prompt para robustecer la entrega de `refresh_token` en el flujo WebServer.
- Nuevas variables de configuración para Salesforce OAuth:
  - `SF_OAUTH_SCOPE` (default: `api refresh_token offline_access`)
  - `SF_OAUTH_PROMPT` (default: `consent`)
- `CreateSalesforceCaseJob` ahora evita reintentos cuando OAuth ya está marcado como desconectado en `SiteSettings`.
- Se reduce ruido operativo por fallas repetidas `invalid_grant` y se mantiene la señal clara de reconexión manual requerida en panel admin.
- Cobertura de tests actualizada para validar el nuevo comportamiento de omisión cuando OAuth está desconectado.

---

## [1.9.0] - 2026-05-20

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

#### QR y Enlaces de Asesores
- Acción de creación de shortlink QR desde la tabla de asesores
- Mejoras de descripción y formato en formularios/infolist de Short Links
- Nuevo flujo de redirección WhatsApp para asesores con helpers UTM

#### Salesforce - Métricas Comerciales de Brokers
- Nuevo comando `salesforce:sync-broker-metrics` para recalcular métricas comerciales desde snapshots locales de oportunidades
- Programación en scheduler cada 15 minutos con `withoutOverlapping()` para mantener datos de brokers actualizados

#### Herramientas y Flujo de Agentes
- Actualización de instrucciones y contexto operativo para agentes (`AGENTS.md` y `copilot-instructions.md`)
- Ajustes de tooling de desarrollo para mejorar consistencia del flujo de asistencia en el repositorio

### 🔄 Cambios

#### Importación CSV de Contactos
- Flujo del wizard ajustado para seleccionar `canal` antes de definir mapeos de columnas.
- Página de progreso de importación registrada en Filament y navegación del panel.
- Mapeo CSV robustecido con normalización de llaves y aliases:
  - `rango_renta` como llave canónica (con limpieza de aliases legacy).
  - `apellido` como llave canónica única (sin duplicar `apellidos`).
  - Teléfonos normalizados a solo dígitos (sin `+` ni espacios).
  - Normalización de texto con reemplazo de `_` por espacios para campos no email.
- Enriquecimiento de UTMs durante importación y sincronización (`utm_source`, `utm_campaign`, `utm_content`, `utm_medium`, `utm_term`) desde aliases de marketing.
- Comando histórico `contact:normalize-rango-renta-key` con soporte `--dry-run` para normalizar registros existentes.
- Cobertura de pruebas actualizada para mapper CSV, normalización histórica y flujo del job de importación.

#### Curator y Tests
- Ajuste de estilo en `MediaForm` (closures y concatenación) para mantener consistencia de formato.
- Normalización de concatenación en test de QR short link para mayor legibilidad.
- Sin cambios funcionales en el flujo de redirección ni en la generación de short links.

#### Salesforce — Etapas de Proyecto
- Normalización centralizada de `etapa` con catálogo canónico y aliases legacy
- Sincronización de proyectos normaliza `Etapa__c` antes de persistir
- API y tablas del panel homologan representación de etapa (filtros, badges y payload)
- Migración de backfill para normalizar valores históricos en `proyectos.etapa`
- Tests unitarios y feature actualizados para cubrir normalización y casos legacy

#### Pricing Dinámico de Proyectos
- Incorporación de `descuento_maximo_unidad` y `descuento_maximo_cotizacion_web` en modelo, migraciones y recursos del panel
- Ajustes de `SiteSettings` para definir fuentes de descuento usadas por el pricing de API
- Cobertura de pruebas ajustada para validar persistencia, exposición y cálculo de descuentos

#### UX de Panel Filament
- Integración de script/asset para dual scroll en tablas extensas del panel
- Ajustes de visibilidad de columnas en Contact Submissions para priorizar lectura operativa (por ejemplo, `rango_renta` oculto por defecto)

#### Salesforce — Refinamiento de Mapeos
- Refinamiento de `SalesforceCaseMapper` para resolver website por canal con reglas más robustas
- Ajustes de mapeo para campos de proyecto legacy/canónicos, incluyendo manejo de `Proyect_ID__c`

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
