Instrucciones para Copilot – Proyecto Laravel 12 + Filament + Salesforce + React

1. Reglas generales del proyecto
El proyecto es backend-first: primero se implementa el backoffice en Laravel 12 con Filament 5, luego se define y construye el frontend en React (Vite).
Siempre usar las últimas versiones estables de los paquetes, respetando compatibilidad con Laravel 12.
El código debe ser claro, tipado (PHP 8.2+), con nombres expresivos, y siguiendo las convenciones oficiales de Laravel en app, config, database y routes.

2. Documentación que debes consultar
Siempre que generes o sugieras código, configuración o arquitectura para:
Laravel (configuración, env, caching, colas, etc.)
Consulta primero la documentación oficial de Laravel 12:

https://laravel.com/docs/12.x/configuration​

Filament (panel administrativo, resources, forms, tables, panels)
Consulta primero la documentación oficial de Filament 5 (Panel Builder, Resources, Pages, Widgets):

https://filamentphp.com/docs/5.x/getting-started​

Si tu sugerencia entra en conflicto con estas documentaciones, prioriza lo que indiquen las docs oficiales de Laravel y Filament.

3. Alcance del backend (Laravel 12 + Filament 5)
3.1. Configuración inicial
Usar composer create-project laravel/laravel con la versión correspondiente a Laravel 12.
Configurar .env para base de datos, cache y colas siguiendo la guía de configuración oficial de Laravel 12.​
Habilitar cache (idealmente Redis o database) para reducir llamadas a Salesforce.

3.2. Panel administrativo con Filament
Instalar Filament Panel Builder para Laravel 12 según la guía oficial, usando panel /admin como entrada del backoffice.

Crear un panel principal para administración, siguiendo el flujo recomendado en “Getting started” de Filament (panel, navegación, auth, etc.).​

Cada entidad relevante (consultas, leads, registros sincronizados desde Salesforce, pagos, logs de integración) debe tener un Filament Resource con:
List: tabla paginada, filtros por estado, fechas, origen.
Create/Edit: formularios con validación y reglas de negocio.
Actions: acciones para forzar re-sincronización, reintentos de pagos, etc.​

3.3. Integración Salesforce (Forrest + SOQL)
Utilizar omniphx/forrest (o un paquete Laravel equivalente) para conectarse a Salesforce via REST, con configuración en .env siguiendo el patrón estándar (SF_AUTH_METHOD, SF_CONSUMER_KEY, SF_INSTANCE_URL, etc.).​

Implementar un servicio de dominio (por ejemplo App\Services\Salesforce\SalesforceService) que:
Exponga métodos para ejecutar consultas SOQL comunes.
Oculte la lógica de autenticación/refresh de Forrest.
Use el sistema de cache de Laravel para almacenar resultados de SOQL.

3.4. Cacheo y sincronización de SOQL
Las consultas SOQL frecuentes deben:
Usar Cache::remember() con claves claras (salesforce:soql:leads:by_email:{email}, etc.) para evitar llamadas repetidas a la API.​

Definir TTLs adecuados según la criticidad de los datos (por ejemplo, 5–15 minutos para datos de lectura frecuente, más corto si es muy dinámico).​

Crear migraciones para almacenar resultados/snapshots relevantes en tablas locales (ej. salesforce_leads, salesforce_accounts, salesforce_queries_log) para reportes y auditoría.
Implementar Jobs / Commands que:
Refresquen periódicamente ciertos datos críticos desde Salesforce (cron).
Sincronicen cambios locales hacia Salesforce cuando aplique.

4. Pasarelas de pago
El backend debe exponer endpoints y lógica de dominio para manejar pagos con:
Transbank (Chile).
Mercado Pago (Latam).
La arquitectura esperada:
Una tabla payments con campos mínimos: id, user_id, gateway, gateway_tx_id, amount, currency, status, metadata, created_at, updated_at.​

Webhooks dedicados para cada gateway, con verificación de firma y lógica idempotente para evitar doble acreditación.
Servicios específicos por pasarela (TransbankService, MercadoPagoService) que:
Preparan la transacción.
Redirigen/entregan URL de pago.
Procesan notificaciones y actualizan payments y las entidades de negocio (ej. órdenes, créditos, etc.).
Crear Filament Resources para:
Ver pagos, filtrar por gateway/estado.
Reintentar procesar algunos pagos (cuando sea seguro).
Ver logs de webhooks.

5. Frontend (React + Vite) – fase posterior
El frontend en React se implementará después de tener el backend estable.
El front debe hablar con el backend Laravel via API (JSON) y respetar:
Autenticación (Sanctum o JWT).
Rutas de negocio expuestas por Laravel para Salesforce y pagos.
Hasta que se definan detalles del frontend, no generar código React a menos que se pida explícitamente.

Cuando se implemente React, priorizar:
Vite.

Código modular (hooks, context o Zustand/Redux según se decida).
Tipado con TypeScript si es posible.

6. Estilo de código y buenas prácticas
No modificar .env en runtime; usar config() y, si se necesita persistencia, usar tablas de settings y comandos/config cache, según las buenas prácticas de configuración de Laravel.

Usar Jobs, Services y Actions para separar lógica de infraestructura, integración y dominio.

Para Filament:
Seguir la estructura recomendada de Resources (forms/tables), Pages y Widgets de acuerdo a la documentación oficial.​

Para caching:
Usar drivers recomendados para producción (Redis o database).
Evitar sobre-cachear datos innecesarios.​

7. Lo que NO debe hacer Copilot
- No inventar configuraciones que contradigan la documentación de Laravel 12 o Filament 5.
- No usar paquetes Laravel obsoletos o incompatibles con Laravel 12 sin justificación.
- No mezclar lógica de negocio de Salesforce directamente en controladores; siempre pasar por servicios/Jobs.
- No generar código de frontend (React) hasta que el backend, panel y API estén razonablemente definidos.
- No crees soluciones o implementaciones que no se han pedido explícitamente en este documento.
- No alucines siempre revisa la documentación oficial antes de sugerir algo nuevo.

8. Siempre hacer:
- Priorizar la documentación oficial de Laravel 12 y Filament 5.
- Mantener el enfoque backend-first.
- Siempre hacer un testeo básico del código generado para asegurar que no haya errores obvios ni de sintaxis.
- Antes de dar instruccion de un comando para la terminal interactivo, explicar antes que opciones se deberian elegir y por qué.
---
description: Instrucciones para Copilot en un proyecto Laravel 12 con Filament, Salesforce y React.