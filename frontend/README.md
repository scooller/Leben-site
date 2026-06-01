# Frontend iLeben

Frontend React + Vite para el sitio público de iLeben. Consume la API de Laravel en `/api/v1`, renderiza el catálogo de proyectos y plantas, y cubre los flujos públicos de contacto y pago.

## Scripts

- `npm run dev`: levanta el frontend en modo desarrollo con Vite.
- `npm run build`: genera el build de producción, prerenderiza rutas y valida SEO.
- `npm run build --production`: ejecuta el build orientado a producción (equivalente recomendado: `npm run build:production`).
- `npm run build:dev`: genera build en modo desarrollo y luego ejecuta prerender + validación SEO.
- `npm run preview`: sirve el build localmente.
- `npm run lint`: ejecuta ESLint sobre el código del frontend.
- `npm run seo:validate`: valida las reglas SEO sin volver a construir.
- `npm run install:webawesome`: instala dependencias usando el token de Web Awesome cuando corresponde.

## Custom commands

Estos comandos son propios del proyecto y ejecutan scripts internos en `frontend/scripts`:

- `npm run build`: corre `vite build` y luego ejecuta, en orden:
	- `node ./scripts/prerender-routes.mjs`
	- `node ./scripts/validate-seo.mjs`
- `npm run build:production`: corre `vite build --mode production` y luego ejecuta prerender + validación SEO.
- `npm run build:dev`: corre `vite build --mode development` y luego ejecuta prerender + validación SEO.
- `npm run seo:validate`: ejecuta solo `node ./scripts/validate-seo.mjs`.
- `node ./scripts/prerender-routes.mjs`: prerenderiza rutas estáticas del frontend.
- `node ./scripts/validate-seo.mjs`: valida metadatos y reglas SEO del build.
- `npm run install:webawesome`: instala dependencias resolviendo el acceso al registro privado de Web Awesome con variables de entorno.

## Requisitos de entorno

Configura un archivo `.env` en `frontend/` con estas variables:

- `VITE_API_URL`: base URL de la API pública.
- `VITE_APP_URL`: URL base del sitio.
- `VITE_TURNSTILE_SITE_KEY`: clave pública de Cloudflare Turnstile para los formularios.
- `VITE_API_AUTH_TOKEN`: token por defecto para llamadas autenticadas.
- `VITE_STAGE_ALIAS_MAP`: mapeo JSON de alias de etapas visibles.
- `VITE_STAGE_KEY_ALIASES`: mapeo JSON de llaves de etapa legacy a llaves canónicas.
- `VITE_STAGE_ALIAS_BY_PROJECT_SLUG`: mapeo JSON de alias por slug de proyecto.
- `WEBAWESOME_NPM_TOKEN`: token opcional para instalar Web Awesome Pro.

Consulta `frontend/.env.example` para la plantilla completa.

## Arranque local

1. Instala dependencias en `frontend/`.
2. Copia `frontend/.env.example` a `frontend/.env` y completa las variables necesarias.
3. Asegura que la API de Laravel esté disponible en la URL definida por `VITE_API_URL`.
4. Ejecuta `npm run dev` para desarrollo o `npm run build` para validar el build de producción.

Si quieres revisar el build generado localmente, ejecuta primero `npm run build` y luego `npm run preview`.

## Estructura funcional

- `src/pages/Home.jsx`: catálogo principal y navegación de proyectos/plantas.
- `src/pages/Contact.jsx`: formulario público de contacto con Turnstile.
- `src/pages/Payment.jsx`: flujo público de pago.
- `src/services/`: clientes e integraciones con API, checkout, sitio, reservas y Web Awesome.
- `src/utils/`: helpers de SEO, alias de etapas, UTM, enlaces externos y datos estructurados.

## Notas de integración

- El frontend usa `@web.awesome.me/webawesome-pro` como librería de UI principal.
- GSAP se usa para animaciones y efectos de scroll cuando aplica.
- La disponibilidad del catálogo debe basarse en `is_paid` e `is_available` devueltos por `/api/v1/plantas`.
- Una planta no disponible puede estar reservada, completada o asociada a un pago completado/autorizado en el backend.
- El build de producción incluye prerender de rutas y validación SEO; `contacto` y `pago` no se prerenderizan.
- `npm run preview` solo sirve correctamente después de generar `dist/` con `npm run build`.

## Documentación útil

- [Web Awesome](https://webawesome.com/docs/)
- [Web Awesome Themes](https://webawesome.com/docs/themes/)
- [GSAP](https://gsap.com/docs/v3/)
- [GSAP ScrollTrigger](https://gsap.com/docs/v3/Plugins/ScrollTrigger)

