# Guia de uso de la API (iLeben)

Guia operativa exhaustiva basada en la implementacion actual del proyecto.

Fuentes verificadas:
- `routes/api.php`
- `routes/web.php`
- `app/Http/Controllers/Api/*`
- `app/Http/Requests/*`
- `app/Http/Middleware/EnsureTokenOriginIsAuthorized.php`
- Modelos Eloquent (`SiteSetting`, `Proyecto`, `Plant`, `PlantReservation`, `Payment`, `FrontendPreviewLink`)
- Suite de pruebas automatizadas en `tests/Feature/Api/*` y `tests/Feature/Feature/Api/*`

---

## 1. Base URL, descubrimiento y protocolos para agentes

### 1.1 Base URL versionada
- `/api/v1`

### 1.2 Descubrimiento OpenAPI base
- **GET** `/api/v1`
Devuelve la especificacion base OpenAPI 3.0.3 en formato JSON describiendo los endpoints, tags y esquema de seguridad Bearer.

### 1.3 Descubrimiento ampliado y protocolos comerciales / agentes
El sistema expone puntos de entrada estandarizados para integraciones modernas, frontends y agentes autonomos:
- **GET** `/openapi.json`: Documento OpenAPI 3.1.0 ampliado con extensiones MPP (Machine Payment Protocol / `x-payment-info`) para operaciones que involucran checkout y reservas.
- **GET** `/.well-known/api-catalog`: Especificacion RFC 9727 (Linkset JSON) con enlaces a descriptor OpenAPI y documentacion.
- **GET** `/.well-known/acp.json`: Descubrimiento de protocolo de comercio para agentes (Agentic Commerce Protocol - ACP 1.0).
- **GET** `/.well-known/ucp`: Especificacion Universal Commerce Protocol (UCP 1.0) con endpoints de catalogo, reservas y checkout.
- **GET** `/.well-known/ai-catalog.json`: Manifiesto ARD (Agentic Resource Discovery 1.0) con identificador did:web y consultas semanticas representativas.
- **GET** `/.well-known/mcp/server-card.json`: Tarjeta de servidor Model Context Protocol (MCP SEP-1649) con transporte streamable-http (`/mcp`).
- **GET** `/.well-known/agent-skills/index.json`: Indice de habilidades de agentes (Agent Skills RFC v0.2.0): `catalog-search`, `unit-reservation` y `auth-md`.
- **GET** `/auth.md` y `/.well-known/oauth-authorization-server`: Especificacion Auth.md y metadatos de autorizacion OAuth 2.0 (RFC 8414).
- **GET** `/llms.txt` y `/.well-known/llms.txt`: Resumen de documentacion en texto plano para modelos LLM.

---

## 2. Seguridad y autenticacion

La API implementa tres niveles de acceso segun el tipo de operacion:

| Nivel | Middleware | Requisitos | Endpoints tipicos |
|---|---|---|---|
| **Nivel 1: Publico absoluto** | Ninguno (o throttle) | Ningun token requerido | `/site-config`, `/contact-submissions`, `/login`, `/register`, `/payments/public-status/{id}` |
| **Nivel 2: Catalogo y Checkout Anonimo** | `token.origin` | Header `Authorization: Bearer <api_token>` + origen autorizado (`Origin`, `Referer` o `X-Authorized-Url`) | `/proyectos`, `/plantas`, `/payment-gateways`, `/reservations`, `/checkout` |
| **Nivel 3: Usuario Autenticado** | `auth:sanctum`, `token.origin` | Bearer token de usuario Sanctum + origen autorizado | `/me`, `/logout`, `/payments`, `/production-sync/export` |

### 2.1 Login y obtencion de token Sanctum
Para operaciones que requieren contexto de usuario registrado o roles administrativos:
- **POST** `/api/v1/login`
- **POST** `/api/v1/register`

Body de Login:
```json
{
  "email": "usuario@dominio.com",
  "password": "tu-password"
}
```

Respuesta exitosa (200):
```json
{
  "user": {
    "id": 1,
    "name": "Usuario Demo",
    "email": "usuario@dominio.com"
  },
  "token": "1|token-plano-sanctum"
}
```

### 2.2 Middleware `token.origin`
Protege el inventario y el proceso transaccional contra scraping no autorizado y reutilizacion fuera de dominio:
1. Valida la existencia del Bearer token en `PersonalAccessToken::findToken(...)`.
2. Verifica que el token no este expirado (`expires_at`).
3. Si el token tiene configurada una URL autorizada (`authorized_url`), valida que coincida con `Origin`, `Referer` o `X-Authorized-Url`.

**Excepcion / Bypass de previsualizacion:**
Si la solicitud cuenta con autorizacion valida de enlace de previsualizacion (`FrontendPreviewLink::isAuthorizedForRequest`), como en entornos de staging o preview de Filament (`preview_token`), el middleware `token.origin` permite el acceso automaticamente sin exigir token estatico.

Errores comunes:
- `401 Token de acceso requerido.` (Falta header Authorization)
- `401 Token de acceso inválido o expirado.` (Token inexistente o caducado)
- `403 La URL de origen no está autorizada para este token.` (Origen no coincide)

---

## 3. Rate limiting

Límites configurados por endpoint y direccion IP:
- **POST** `/api/v1/login`: 5 intentos por minuto (`throttle:5,1`)
- **POST** `/api/v1/register`: 5 intentos por minuto (`throttle:5,1`)
- **POST** `/api/v1/contact-submissions`: 10 envíos por minuto (`throttle:10,1`)
- **GET** `/s/{slug}` (redirección shortlinks): 120 por minuto (`throttle:120,1`)
- **GET** `/go/asesores/{asesor}/whatsapp`: 120 por minuto (`throttle:120,1`)

---

## 4. Endpoints publicos (sin token)

### 4.1 Configuracion del sitio
- **GET** `/api/v1/site-config`

Retorna la parametrizacion dinamica para renderizar el frontend (colores de marca, logos Curator, SEO, scripts de conversion y estado de mantenimiento).

**Comportamiento de seguridad:**
- Si la solicitud se realiza **sin** un Bearer token valido, la configuracion sensible de pasarelas (`gateway_transbank_config`, `gateway_mercadopago_config`, `gateway_manual_config`), `price_source` y `price_percentage_source` se omiten de la respuesta.
- Si se envia un Bearer token valido en `Authorization: Bearer <token>`, el payload incluye el bloque completo `payment_gateways`.

Campos clave devueltos:
- `site_name`, `site_description`, `site_url`
- `mostrar_plantas`: booleano general de visibilidad de inventario
- `evento_sale`: booleano indicador de campana sale activa
- `plants_per_page`: cantidad de unidades por pagina configurada (default: 12)
- `seo`: metadatos, og_image dinamica, datos del evento sale (`sale_event`), campana sale forzada (`sale_campaign_override`, `sale_utm_campaign`) y defaults UTM (`utm_campaign_default`)
- `conversion_scripts`: integracion de scripts post-contacto y post-pago
- `hero`: imagenes desktop/mobile y posters de video para home y contacto
- `footer_menu`, `contact_page`, `social`

### 4.2 Envio de formulario de contacto
- **POST** `/api/v1/contact-submissions`

Registra consultas de clientes, enriquece parametros UTM, asocia canal de contacto y encola la creacion automatica del Lead en Salesforce.

Headers opcionales:
- `X-Contact-Channel`: slug o identificador del canal (si no viene en el body).
- `Origin` / `Referer`: utilizados para autocompletar `utm_site` si no fue enviado.

Body JSON:
```json
{
  "channel": "sale",
  "fields": {
    "name": "Juan Perez",
    "email": "juan@example.com",
    "phone": "+56911111111",
    "message": "Consulta sobre departamento 2D en Torre Central",
    "proyecto": "torre-central",
    "comuna": "Santiago",
    "rango_renta": "1.500.000 a 2.000.000",
    "utm_source": "google",
    "utm_medium": "cpc",
    "utm_campaign": "lanzamiento"
  },
  "turnstile_token": "0.xxxx.yyyy"
}
```

Reglas y comportamiento:
- `channel`: obligatorio (por body o header `X-Contact-Channel`).
- `fields`: array asociativo con los valores del formulario. El telefono se normaliza persistentemente solo con digitos.
- `turnstile_token`: si Cloudflare Turnstile esta habilitado en configuracion, es validado del lado del servidor.
- `utm_site`: si no se especifica en `fields`, el backend lo infiere del dominio de origen.
- Respuesta exitosa: `201 Created` con `{"message": "Tu mensaje fue enviado correctamente.", "id": 123}`.

### 4.3 Estado publico de pago
- **GET** `/api/v1/payments/public-status/{id}?token={uuid}`

Permite a la pantalla de resultado del frontend consultar el estado de una transaccion sin necesidad de autenticacion de usuario.
- Requiere parametro query `token` correspondiente al `public_status_token` UUID emitido durante el checkout.
- Retorna: `id`, `gateway`, `gateway_tx_id`, `amount`, `currency`, `status`, `status_label`, `updated_at`.
- Codigos de error: `422` (falta parametro token), `404` (pago o token inexistente).

---

## 5. Endpoints de catalogo, reservas y checkout (token.origin)

Estos endpoints requieren `Authorization: Bearer <api_token>` y origen autorizado, pero **no** exigen sesion de usuario logueado (`auth:sanctum`).

### 5.1 Proyectos

#### Listado de proyectos
- **GET** `/api/v1/proyectos`

Filtros query disponibles:
- `region`: nombre exacto de la region.
- `comuna`: nombre exacto de la comuna.
- `etapa`: etapa de desarrollo normalizada (`blanco`, `verde`, `entrega_inmediata`, `en_construccion`, `venta`, `pre_venta`).
- `q`: busqueda por texto libre en nombre, comuna, region o direccion.
- `entrega_inmediata`: `1` / `0` o `true` / `false`.
- `tipo`: tipo o tipos de proyecto (admite valor unico o array/separado por comas).
- `perPage`: paginacion (1 a 100, default: 15).
- `fields` / `campos`: seleccion de campos especificos separados por coma (ej: `id,name,comuna,precio_desde,tipologias`).

Campos calculados por defecto:
- `precio_desde`: Menor `precio_lista` entre todas las plantas activas del proyecto. `null` si no posee plantas.
- `tipologias`: Agrupacion estructurada de plantas activas por dormitorios (`programa`), banos (`programa2`) y tipo de producto.

Ejemplo de elemento en `tipologias`:
```json
{
  "programa": "2 dormitorios",
  "programa2": "2 banos",
  "tipo_producto": "DEPARTAMENTO",
  "cantidad": 8,
  "precio_desde": 3150.00,
  "superficie_util_min": 52.4,
  "superficie_util_max": 58.2
}
```

#### Detalle de proyecto
- **GET** `/api/v1/proyectos/{id}`

Parametros query:
- `include_plantas`: `1` o `true` para incluir array de unidades (`plantas`).
- `include_asesores`: `1` o `true` para incluir array de asesores activos asignados (`asesores`).
- `evento_sale`: `1` o `0` para calcular precios y descuentos de plantas bajo modalidad sale.
- `fields` / `campos`: seleccion de campos del proyecto.

Estructura de asesor incluido:
```json
{
  "id": 4,
  "full_name": "Maria Gonzalez",
  "first_name": "Maria",
  "last_name": "Gonzalez",
  "email": "mgonzalez@ileben.cl",
  "whatsapp_owner": "+56987654321",
  "resolved_avatar_url": "https://dominio.com/curator/media/avatar.webp"
}
```

### 5.2 Plantas (Unidades / Inventario)

#### Listado de plantas
- **GET** `/api/v1/plantas`

Filtros soportados:
- `proyecto_id` o `project_id`: ID numerico o lista separada por comas.
- `salesforce_proyecto_id`: Salesforce ID del proyecto (admite lista).
- `project_slug` o `slug`: slug del proyecto.
- `catalog_slug`: resuelve coincidencias contra slug de proyecto o comuna.
- `comuna_slug`: slug de comuna.
- `comuna`, `provincia`, `region`: filtros geograficos del proyecto.
- `programa`: filtro de dormitorios (`ST`, `1`, `2`, `2D`...).
- `programa2`: filtro de banos (`1`, `2`, `1B`...).
- `piso`: numero de piso.
- `orientacion`: orientacion cardinal (`Norte`, `Sur`, `Oriente`, `Poniente`, etc.).
- `tipo_producto`: tipo exacto (ej: `DEPARTAMENTO`, `CASA`).
- `tipo_producto_slug` o `tipo_slug`: slug del tipo de producto.
- `entrega`: etapa de entrega del proyecto.
- `disponible` o `available`: `1` (sin reserva activa ni pago completado), `0` (con reserva o pagada).
- `is_active`: `1` (solo activas), `0` (solo inactivas). Si se omite, retorna todas.
- `evento_sale`: `1` (solo unidades con `unidad_sale = true` con descuento de unidad), `0` (solo unidades sin sale). Si se omite, no filtra por sale y aplica el descuento general del proyecto.
- `min_precio`, `max_precio`: rango sobre `precio_base`.
- `perPage`: cantidad por pagina (default determinado por `plants_per_page` de SiteSetting, max 100).

Campos dinamicos devueltos en cada planta:
- `precio_final`: Precio calculado considerando precio de lista y descuento aplicable (`porcentaje_maximo_unidad` si `evento_sale=1`, o `descuento_defecto_cotizacion_web` del proyecto en modo normal).
- `is_available`: booleano (`true` si no tiene reserva activa ni pago finalizado).
- `is_paid`: booleano (`true` si cuenta con reserva o pago completado).
- `cover_image_url`, `interior_image_url`, `cover_image_media`, `interior_image_media`.
- `asesores`: lista de asesores asignados a la unidad (o heredados del proyecto si la unidad no tiene asesor asignado).

#### Detalle de planta por ID
- **GET** `/api/v1/plantas/{id}`

Parametros query:
- `evento_sale`: `1` o `0` para resolver el calculo de precio con descuento de evento.

#### Detalle de planta por proyecto y unidad (Ruta semantica)
- **GET** `/api/v1/plantas/proyecto/{projectSlug}/unidad/{unitName}`

Permite consultar directamente una unidad usando el slug del proyecto y el nombre de la planta (ej: `/plantas/proyecto/torre-central/unidad/Depto-101` o `/plantas/proyecto/torre-central/unidad/depto-101`).
- Soporta parametro query `evento_sale`.

#### Catalogo de filtros de ubicacion
- **GET** `/api/v1/plantas/filtros-ubicacion`

Devuelve las opciones unicas actualmente disponibles en proyectos y plantas activas:
```json
{
  "regions": ["Metropolitana de Santiago", "Los Lagos"],
  "comunas": ["Santiago", "Providencia", "Puerto Varas"],
  "comunas_by_region": {
    "Metropolitana de Santiago": ["Providencia", "Santiago"],
    "Los Lagos": ["Puerto Varas"]
  },
  "orientaciones": ["Nor-Oriente", "Sur", "Poniente"],
  "tipos_producto": ["DEPARTAMENTO", "CASA"],
  "pisos": ["2", "3", "4"],
  "entregas": ["En Blanco", "En Verde", "Entrega Inmediata"]
}
```

### 5.3 Pasarelas de pago disponibles
- **GET** `/api/v1/payment-gateways?plant_id={id}`

Devuelve las pasarelas habilitadas para la unidad consultada (valida que el proyecto tenga codigo de comercio para Transbank o datos de cuenta bancaria para pago manual).

Respuesta:
```json
{
  "gateways": [
    {
      "id": "transbank",
      "name": "Webpay (Transbank)",
      "flow": "redirect",
      "description": "Paga con tarjeta de credito o debito"
    },
    {
      "id": "manual",
      "name": "Transferencia Bancaria",
      "flow": "manual",
      "description": "Transfiere directamente a la cuenta del proyecto"
    }
  ],
  "count": 2
}
```

### 5.4 Reservas temporales (Plant Reservations)

Permite bloquear temporalmente una unidad durante el proceso de seleccion o pago.

- **POST** `/api/v1/reservations`: Crear reserva temporal.
  - Body: `{"plant_id": 10, "session_token": "opcional-uuid"}`
  - Respuesta 201: Contiene `session_token`, `expires_at` y `remaining_seconds`.
  - Si la unidad ya esta reservada por otra sesion devuelve `409 Conflict`.
- **GET** `/api/v1/reservations/planta/{plantId}` (Alias: `/api/v1/reservations/plant/{plantId}`):
  - Consulta publica de estado de reserva para despliegue de badges de disponibilidad.
- **DELETE** `/api/v1/reservations/{sessionToken}`:
  - Libera la reserva si el usuario cancela o cierra el dialogo de checkout.

### 5.5 Inicio de Checkout
- **POST** `/api/v1/checkout`

Inicia la pasarela seleccionada para reservar o comprar una unidad. Si el cliente no esta autenticado, el sistema busca o crea automaticamente un usuario con rol `customer`.

Body:
```json
{
  "plant_id": 10,
  "quantity": 1,
  "gateway": "transbank",
  "name": "Juan Perez",
  "email": "juan@example.com",
  "phone": "+56911111111",
  "rut": "12345678-5",
  "session_token": "token-reserva-si-aplica"
}
```

Pasarelas soportadas:
1. `transbank`: Genera transaccion Webpay Plus. Devuelve `redirect_url`, `token`, `payment_id`, `payment_status_token`.
2. `mercadopago`: Genera preferencia Mercado Pago. Devuelve `redirect_url`, `preference_id`.
3. `manual`: Requiere `session_token` activo. Devuelve instrucciones de transferencia, datos bancarios del proyecto (`bank_accounts`), codigo de referencia y fecha limite (`expires_at`).

---

## 6. Endpoints protegidos para usuarios autenticados (`auth:sanctum` + `token.origin`)

Estos endpoints requieren sesion de usuario iniciada mediante Bearer token Sanctum y validacion de origen.

### 6.1 Perfil y sesion
- **GET** `/api/v1/me`: Datos del usuario autenticado.
- **POST** `/api/v1/logout`: Revoca el token actual del usuario.

### 6.2 Exportacion para sincronizacion de produccion (Solo Admin)
- **GET** `/api/v1/production-sync/export`
- Requiere que el usuario autenticado sea administrador (`$request->user()->isAdmin()`).
- Devuelve snapshot completo en formato JSON para importacion en entornos locales o de staging:
  - `site_settings`: Configuracion global y branding del sitio.
  - `projects`: Catalogo de proyectos con especificaciones comerciales y financieras.
  - `advisors`: Asesores comerciales con `salesforce_id`, contacto, WhatsApp, avatar y sus proyectos asociados en tabla pivote (`proyectos_salesforce_ids`).
  - `plants`: Unidades habitacionales con `asesor_salesforce_id` para re-vinculacion automatica con sus asesores correspondientes.

### 6.3 Gestion directa de pagos del usuario
- **POST** `/api/v1/payments`: Crear registro de pago.
- **GET** `/api/v1/payments`: Listado paginado de los pagos asociados al usuario autenticado.
- **GET** `/api/v1/payments/{id}`: Detalle de pago propio.
- **POST** `/api/v1/payments/{id}/manual-proof`:
  - Permite adjuntar comprobante de transferencia bancaria para un pago manual.
  - Content-Type: `multipart/form-data`.
  - Campos: `proof` (archivo requerido, formatos: jpg, jpeg, png, pdf, heic, heif; max 5MB), `notes` (opcional).

---

## 7. Endpoints web, retornos y utilitarios (fuera de `/api/v1`)

Definidos en `routes/web.php`:

### 7.1 Retornos y webhooks de pasarelas
- **Transbank:**
  - **GET** `/payments/transbank/redirect`: Pagina puente que despacha POST `token_ws` hacia Transbank.
  - **GET|POST** `/payments/transbank/return`: Retorno del navegador / confirmacion tras completar el pago en Webpay.
- **Mercado Pago:**
  - **POST** `/payments/mercadopago/webhook`: Webhook IPN para notificaciones de pago asincronas.
  - **GET** `/payments/mercadopago/return`: Retorno del cliente desde Mercado Pago.
- **Paginas de resultado del checkout:**
  - **GET** `/payments/success/{payment?}`
  - **GET** `/payments/failed/{payment?}`
  - **GET** `/payments/pending/{payment?}`

### 7.2 Acortador y enlaces dinamicos de asesores
- **GET** `/s/{slug}`: Redireccion de enlace corto con registro de clic, User-Agent, IP y propagacion automatica de parametros UTM.
- **GET** `/go/asesores/{asesor}/whatsapp`: Redireccion directa al WhatsApp del asesor comercial preservando campana de origen.
- **GET** `/curator/{path}`: Servidor seguro de archivos multimedia gestionados por Filament Curator.

---

## 8. Ejemplos cURL rapidos

### Consulta de catalogo de proyectos con origen autorizado
```bash
curl -X GET "https://tu-dominio.com/api/v1/proyectos?region=Metropolitana&perPage=10" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Origin: https://sale.ileben.cl"
```

### Consulta de plantas con filtro de disponibilidad y evento sale
```bash
curl -X GET "https://tu-dominio.com/api/v1/plantas?disponible=1&evento_sale=1&programa=2D" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Origin: https://sale.ileben.cl"
```

### Detalle semantico de unidad
```bash
curl -X GET "https://tu-dominio.com/api/v1/plantas/proyecto/edificio-parque/unidad/Depto-402" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Origin: https://sale.ileben.cl"
```

### Envio de formulario de contacto
```bash
curl -X POST "https://tu-dominio.com/api/v1/contact-submissions" \
  -H "Content-Type: application/json" \
  -H "Origin: https://sale.ileben.cl" \
  -d '{
    "channel": "sale",
    "fields": {
      "name": "Maria Lopez",
      "email": "mlopez@example.com",
      "phone": "+56998877665",
      "message": "Solicitud de cotizacion",
      "proyecto": "edificio-parque"
    }
  }'
```

### Reserva temporal de unidad
```bash
curl -X POST "https://tu-dominio.com/api/v1/reservations" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Origin: https://sale.ileben.cl" \
  -H "Content-Type: application/json" \
  -d '{"plant_id": 25}'
```

### Iniciar checkout con Webpay (Transbank)
```bash
curl -X POST "https://tu-dominio.com/api/v1/checkout" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -H "Origin: https://sale.ileben.cl" \
  -H "Content-Type: application/json" \
  -d '{
    "plant_id": 25,
    "quantity": 1,
    "gateway": "transbank",
    "name": "Maria Lopez",
    "email": "mlopez@example.com",
    "phone": "+56998877665",
    "rut": "15234567-8"
  }'
```

### Exportar snapshot de produccion (Requiere rol Admin y origen autorizado)
```bash
curl -X GET "https://admin.ileben.cl/api/v1/production-sync/export" \
  -H "Authorization: Bearer TU_SANCTUM_ADMIN_TOKEN" \
  -H "Origin: http://127.0.0.1:8000/admin"
```

---

## 9. Notas operativas

1. **Token API en Catalogo:** Todo el catalogo de proyectos, plantas y flujo anonimo de checkout esta protegido por `token.origin`. Se debe configurar un token en Sanctum con la `authorized_url` del frontend consumidor (o wildcard si aplica).
2. **Previsualizacion sin Token:** Enlaces con `preview_token` valido generado desde Filament (`FrontendPreviewLink`) tienen pase automatico para acceder al catalogo y configuracion sin levantar error 401/403.
3. **Visibilidad de Pasarelas:** La configuracion privada de credenciales de pago en `/api/v1/site-config` no se entrega a clientes publicos no autenticados para evitar fuga de configuracion sensible.
4. **Calculo de Precios:** El campo `precio_final` de cada planta computa automaticamente el descuento aplicable:
   - Si no hay evento sale o `evento_sale=0`: utiliza `descuento_defecto_cotizacion_web` del proyecto.
   - Si `evento_sale=1`: filtra exclusivamente unidades `unidad_sale=true` y descuenta segun `porcentaje_maximo_unidad`.
5. **Separacion de Responsabilidades:** Las operaciones transaccionales complejas (iniciar checkout, webhook de pasarela, retorno del navegador) estan separadas: la API solo inicia la transaccion (`/api/v1/checkout`) y entrega las URLs de redireccion hacia rutas web (`/payments/*`) encargadas de procesar la respuesta del proveedor.
