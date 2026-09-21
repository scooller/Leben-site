# Auth.md - iLeben Agent Authentication and Registration

Este documento define el protocolo y directrices de autenticación y registro para Agentes de Inteligencia Artificial (AI Agents) que interactúan con la plataforma iLeben.

## Información General

- **Audiencia**: Agentes de IA autónomos, asistentes LLM, sistemas multiagente y servicios de integración comercial.
- **Servidor de Autorización OAuth 2.0**: `https://sale.ileben.cl/.well-known/oauth-authorization-server`
- **Metadatos de Recursos Protegidos (RFC 9728)**: `https://sale.ileben.cl/.well-known/oauth-protected-resource`
- **OpenID Connect Discovery**: `https://sale.ileben.cl/.well-known/openid-configuration`
- **Documentación API / OpenAPI**: `https://sale.ileben.cl/openapi.json`

## Registro Dinámico de Agentes (Agent Provisioning)

Los agentes de IA pueden registrarse o solicitar credenciales de acceso a través del endpoint de registro:

- **Endpoint de Registro**: `POST https://sale.ileben.cl/agent/register`
- **Content-Type**: `application/json`

### Tipos de Identidad Soportados
1. `anonymous`: Agentes en modo consulta y navegación de proyectos sin sesión de usuario final.
2. `identity_assertion`: Agentes que representan a un usuario verificado (ej. `verified_email` o `urn:ietf:params:oauth:token-type:id-jag`).

### Tipos de Credenciales Soportadas
- `bearer_token`: Tokens de acceso Bearer temporales.
- `api_key`: Clave API para comunicación directa entre agentes y servicios.

## Uso de Credenciales en Peticiones

Todas las peticiones a endpoints protegidos deben incluir la credencial en la cabecera HTTP estándar:

```http
Authorization: Bearer <access_token>
```

## Alcances Disponibles (Scopes)

- `read`: Acceso de lectura general.
- `write`: Acceso de escritura general.
- `catalog:read`: Consulta del catálogo de proyectos inmobiliarios y departamentos (plantas).
- `reservations:write`: Creación y seguimiento de reservas de unidades.
- `payments:write`: Inicio y consulta de transacciones de pago.

## Flujos de Reclamo y Revocación (Claim & Revocation)

- **Endpoint de Reclamo de Identidad**: `POST https://sale.ileben.cl/agent/claim`
- **Endpoint de Revocación de Tokens**: `POST https://sale.ileben.cl/oauth/revoke`
