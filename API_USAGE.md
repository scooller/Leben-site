# Guia de uso de la API (iLeben)

Guia operativa basada en la implementacion actual del proyecto.

Fuentes verificadas:
- routes/api.php
- app/Http/Controllers/Api/*
- app/Http/Requests/*
- app/Http/Middleware/EnsureTokenOriginIsAuthorized.php
- routes/web.php (retornos y webhooks de pago)
- git log reciente en main (HEAD: 6804179)

## 1. Base URL y descubrimiento

Base URL versionada:
- /api/v1

Endpoint de descubrimiento rapido:
- GET /api/v1

Este endpoint devuelve un JSON estilo OpenAPI base con paths, tags y esquema de seguridad.

## 2. Seguridad y autenticacion

### 2.1 Login y token Sanctum

Para obtener token:
- POST /api/v1/login

Body:
```json
{
  "email": "admin@dominio.com",
  "password": "tu-password"
}
```

Respuesta esperada:
```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@dominio.com"
  },
  "token": "1|token-plano-sanctum"
}
```

### 2.2 Middleware token.origin (obligatorio en la mayoria de endpoints)

Ademas del Bearer token, la API valida origen autorizado por token.

Requisitos:
- Header Authorization: Bearer <token>
- Header de origen: Origin o Referer o X-Authorized-Url
- El origen debe coincidir con la URL autorizada del token (si el token tiene authorized_url configurada)

Errores comunes:
- 401 Token de acceso requerido
- 401 Token de acceso invalido o expirado
- 403 La URL de origen no esta autorizada para este token

## 3. Rate limiting

- POST /api/v1/login: throttle 5 por minuto
- POST /api/v1/register: throttle 5 por minuto
- POST /api/v1/contact-submissions: throttle 10 por minuto

## 4. Endpoints publicos

### 4.1 Descubrimiento y configuracion

- GET /api/v1
- GET /api/v1/site-config

Notas:
- /api/v1/site-config usa SiteSetting::forFrontend(...)
- Si se envia Bearer token valido, el payload puede incluir datos adicionales para cliente autorizado

### 4.2 Contacto

- POST /api/v1/contact-submissions

Body minimo recomendado:
```json
{
  "channel": "sale",
  "fields": {
    "name": "Juan Perez",
    "email": "juan@example.com",
    "message": "Quiero mas informacion",
    "phone": "+56911111111",
    "proyecto": "torre-central",
    "comuna": "Santiago",
    "utm_source": "google",
    "utm_medium": "cpc",
    "utm_campaign": "invierno"
  },
  "turnstile_token": "token-si-esta-configurado"
}
```

Reglas relevantes:
- channel se resuelve por body channel o header X-Contact-Channel
- fields es obligatorio y dinamico (depende de SiteSettings/canal)
- Si Turnstile esta habilitado, turnstile_token es obligatorio y se valida server-side
- El backend enriquece utm_site automaticamente con Origin/Referer/X-Source-Site cuando falta

Respuesta 201:
```json
{
  "message": "Tu mensaje fue enviado correctamente.",
  "id": 123
}
```

### 4.3 Estado publico de pago

- GET /api/v1/payments/public-status/{id}?token={uuid}

Uso:
- Se utiliza para pantalla publica de resultado de pago
- Requiere token de estado (public_status_token) generado durante checkout

Respuestas:
- 200: estado del pago
- 422: falta token query param
- 404: token invalido

## 5. Endpoints de catalogo (sin auth:sanctum, pero con token.origin)

Estos endpoints no piden sesion de usuario, pero SI requieren Bearer token valido y origen autorizado.

- GET /api/v1/proyectos
- GET /api/v1/proyectos/{id}
- GET /api/v1/plantas
- GET /api/v1/plantas/{id}
- GET /api/v1/plantas/filtros-ubicacion
- GET /api/v1/plantas/proyecto/{projectSlug}/unidad/{unitName}

### 5.1 Proyectos

Filtros soportados (principales):
- region
- comuna
- etapa
- q (busqueda)
- entrega_inmediata (true/false)
- tipo (acepta lista)
- perPage
- fields o campos (seleccion de campos)
- include_plantas (en detalle)
- include_asesores (en detalle) — agrega array `asesores` con asesores activos del proyecto

- Campos de cada asesor: id, full_name, first_name, last_name, email, whatsapp_owner, resolved_avatar_url

- resolved_avatar_url prioriza imagen Curator media sobre avatar_url estatico

- Sin este parametro, `asesores` se omite de la respuesta

Ejemplo (detalle con plantas y asesores):
```bash
curl -H "Authorization: Bearer TOKEN" \
  -H "Origin: https://frontend.cliente.com" \
  "https://tu-dominio.com/api/v1/proyectos/3?include_plantas=1&include_asesores=1"
```

Ejemplo (listado simple):
```bash
curl -H "Authorization: Bearer TOKEN" \
  -H "Origin: https://frontend.cliente.com" \
  "https://tu-dominio.com/api/v1/proyectos?region=Metropolitana&perPage=12"
```

### 5.2 Plantas

Filtros soportados (principales):
- proyecto_id o project_id — filtra por ID de proyecto (accepta lista separada por coma)
- salesforce_proyecto_id — filtra por Salesforce ID del proyecto
- project_slug o slug — filtra por slug del proyecto
- comuna_slug
- catalog_slug
- comuna, provincia, region
- programa (dormitorios), programa2 (banos)
- piso
- orientacion
- tipo_producto, tipo_producto_slug, tipo_slug
- entrega
- disponible o available
- is_active — filtro por estado activo de la planta. Sin este parámetro se retornan todas las plantas (activas e inactivas). 1=solo activas, 0=solo inactivas
- evento_sale — filtro evento sale. Solo aplica cuando se envía explícitamente via URL. 1=solo unidades sale con pricing evento, 0=no sale con pricing normal. Sin parámetro no filtra y usa pricing normal (descuento del proyecto)
- min_precio, max_precio
- perPage, page

Campos utiles en respuesta:
- is_available
- is_paid
- precio_final
- cover_image_media e interior_image_media
- cover_image_url e interior_image_url
- asesores
- proyecto

## 6. Endpoints protegidos (auth:sanctum + token.origin)

Estos endpoints requieren usuario autenticado + Bearer token + origen autorizado:

- GET /api/v1/me
- POST /api/v1/logout
- GET /api/v1/production-sync/export
- GET /api/v1/payment-gateways
- POST /api/v1/checkout
- GET /api/v1/reservations/planta/{plantId}
- POST /api/v1/reservations
- DELETE /api/v1/reservations/{sessionToken}
- POST /api/v1/payments
- GET /api/v1/payments
- GET /api/v1/payments/{id}
- POST /api/v1/payments/{id}/manual-proof

### 6.1 Checkout

- POST /api/v1/checkout

Body requerido:
```json
{
  "plant_id": 10,
  "quantity": 1,
  "gateway": "transbank",
  "name": "Juan Perez",
  "email": "juan@example.com",
  "phone": "+56911111111",
  "rut": "12345678-5",
  "session_token": "opcional-no-manual"
}
```

Reglas clave:
- gateway: transbank, mercadopago, manual
- session_token es obligatorio si gateway = manual
- Para manual, debe existir reserva activa valida

Respuesta exitosa tipica:
- transbank: gateway, redirect_url, token, payment_id, payment_status_token
- mercadopago: gateway, redirect_url, preference_id
- manual: flow, payment_id, reference, instructions, bank_accounts, expires_at

### 6.2 Pasarelas disponibles

- GET /api/v1/payment-gateways?plant_id={id}

Respuesta:
```json
{
  "gateways": [
    {
      "id": "transbank",
      "name": "Webpay (Transbank)",
      "flow": "redirect",
      "description": "Paga con tarjeta de credito o debito"
    }
  ],
  "count": 1
}
```

### 6.3 Reservas

- POST /api/v1/reservations

Body:
```json
{
  "plant_id": 10
}
```

Respuesta 201:
```json
{
  "reservation": {
    "id": 90,
    "session_token": "abc123...",
    "plant_id": 10,
    "status": "active",
    "expires_at": "2026-06-02T19:10:00Z",
    "remaining_seconds": 900
  }
}
```

- GET /api/v1/reservations/planta/{plantId}
- DELETE /api/v1/reservations/{sessionToken}

Nota importante:
- La ruta correcta es /reservations/planta/{plantId}

### 6.4 Pagos

- POST /api/v1/payments
- GET /api/v1/payments
- GET /api/v1/payments/{id}

Comprobante manual:
- POST /api/v1/payments/{id}/manual-proof
- Content-Type: multipart/form-data
- Campos:
  - proof (requerido, jpg/jpeg/png/pdf/heic/heif, max 5MB)
  - notes (opcional)

## 7. Endpoints web de retorno/webhook de pasarelas (fuera de /api/v1)

Definidos en routes/web.php:

Transbank:
- GET /payments/transbank/redirect
- GET|POST /payments/transbank/return

Mercado Pago:
- POST /payments/mercadopago/webhook
- GET /payments/mercadopago/return

Paginas de resultado:
- GET /payments/success/{payment?}
- GET /payments/failed/{payment?}
- GET /payments/pending/{payment?}

## 8. Ejemplos cURL rapidos

### Login
```bash
curl -X POST "https://tu-dominio.com/api/v1/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@dominio.com","password":"secret"}'
```

### Catalogo de plantas con token.origin
```bash
curl "https://tu-dominio.com/api/v1/plantas?proyecto_id=3&disponible=1" \
  -H "Authorization: Bearer TOKEN" \
  -H "Origin: https://frontend.cliente.com"
```

### Reserva + checkout manual
```bash
curl -X POST "https://tu-dominio.com/api/v1/reservations" \
  -H "Authorization: Bearer TOKEN" \
  -H "Origin: https://frontend.cliente.com" \
  -H "Content-Type: application/json" \
  -d '{"plant_id":10}'

curl -X POST "https://tu-dominio.com/api/v1/checkout" \
  -H "Authorization: Bearer TOKEN" \
  -H "Origin: https://frontend.cliente.com" \
  -H "Content-Type: application/json" \
  -d '{
    "plant_id":10,
    "quantity":1,
    "gateway":"manual",
    "name":"Juan Perez",
    "email":"juan@example.com",
    "phone":"+56911111111",
    "rut":"12345678-5",
    "session_token":"TOKEN_RESERVA"
  }'
```

## 9. Notas operativas

- En este proyecto, incluso endpoints de catalogo requieren token API por middleware token.origin.
- Si el frontend recibe 403 en API con token valido, revisar Origin/Referer/X-Authorized-Url contra authorized_url del token.
- Para integraciones de pago, usar /api/v1/checkout como punto de entrada y dejar retornos/webhooks en rutas web.
- Para documentacion tecnica extensa de pagos, revisar PAYMENTS.md.
