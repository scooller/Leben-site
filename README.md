# iLeben - Plataforma de Venta de Proyectos

Backend-first SPA con Laravel 12, Filament 5, React 19 y Web Awesome.

## 📋 Stack Tecnológico

### Backend
- **Laravel 12** - Framework PHP moderno
- **Filament 5** - Panel administrativo SDUI
- **PHP 8.4** - Lenguaje de programación
- **MySQL 8** - Base de datos
- **Salesforce API** (omniphx/forrest) - Integración CRM

### Frontend
- **React 19** - UI library
- **Vite** - Build tool
- **Web Awesome 3.2.1** - Design system
- **Tailwind CSS 4** - Utility-first CSS
- **GSAP** - Animaciones

### Gestión de Medios
- **Filament Curator** - Gestor centralizado de archivos/imágenes
- **CropperJS** - Editor de imágenes

### Pasarelas de Pago
- **Transbank** - TCPago Chile
- **Mercado Pago** - Latam
- **Manual** - Configuración custom

## 🏗️ Arquitectura

```
app/
├── Models/               # Eloquent Models
├── Filament/
│   ├── Resources/        # CRUD Resources
│   ├── Pages/            # Custom Pages
│   └── Widgets/          # Dashboard Widgets
├── Services/
│   ├── Salesforce/       # Integración SOQL
│   └── Payment/          # Servicios de pasarelas
├── Http/Controllers/     # API endpoints
├── Enums/                # Enums: PaymentGateway, PaymentStatus
└── Contracts/            # Interfaces

resources/
├── css/                  # Estilos Filament + Tailwind
└── js/                   # JavaScript antiguo (deprecated)

frontend/
├── src/
│   ├── components/       # React components
│   ├── pages/            # Page layouts
│   ├── context/          # React Context
│   ├── hooks/            # Custom hooks
│   └── styles/           # SCSS modules
└── dist/                 # Build output (Vite)
```

## 📦 Características Principales

### Panel Administrativo (Filament)
- ✅ **Autenticación** - Laravel Sanctum + sessions
- ✅ **Proyectos** - CRUD con Transbank commerce code por proyecto
- ✅ **Usuarios** - Gestión de cuentas
- ✅ **Plantas** - Catálogo de plantas con sincronización
- ✅ **Pagos** - Registro de transacciones
- ✅ **Configuración Global** - SiteSettings (9+ tabs)
  - General, Banner, Branding, Colores, Tipografía
  - SEO, Contacto, Redes Sociales, Personalización
  - Pasarelas de Pago, Mantenimiento
- ✅ **Gestor de Archivos** - Curator (File Manager centralizado)
- ✅ **Modo Mantenimiento** - RichEditor + HTML mode + Web Awesome dialog

### Integración Salesforce
- ✅ **SOQL Queries** - Consultas a fuerza de ventas
- ✅ **Caching** - Cache de resultados SOQL (configurable por TTL)
- ✅ **Sincronización** - Jobs para sincronizar datos
- ✅ **Logging** - Auditoría de operaciones

### Frontend React
- ✅ **Home Page** - Hero section + banner promocional
- ✅ **Maintenance Mode** - Modal con Web Awesome dialog
- ✅ **SiteConfig Context** - Datos globales (logo, theme, etc)
- ✅ **Responsive Design** - Mobile-first con Web Awesome
- ✅ **Themes** - 11 temas Web Awesome preinstalados

### Gestión de Medios (Curator)
- ✅ **Centralizado** - Single File Manager en `/admin/media`
- ✅ **Integrado** - Logo, favicon, banner, maintenance images
- ✅ **RichEditor** - Attachments vía AttachCuratorMediaPlugin
- ✅ **Database** - Tabla `curator` para metadata de archivos
- ✅ **Editor** - CropperJS para redimensionar

## 🚀 Instalación

### Requisitos
- PHP 8.4+
- Composer 2.x
- Node.js 18+
- MySQL 8+

### Setup Inicial

```bash
# 1. Clonar repo
git clone <repo-url> sale-ileben
cd sale-ileben

# 2. Instalar dependencias backend
composer install

# 3. Configurar .env
cp .env.example .env
php artisan key:generate

# 4. Configurar Salesforce en .env
# SF_AUTH_METHOD=username-password
# SF_CONSUMER_KEY=xxx
# SF_INSTANCE_URL=https://xxx.salesforce.com

# 5. Ejecutar migraciones
php artisan migrate

# 6. Instalar dependencias frontend
cd frontend
npm install

# 7. Build frontend
npm run build
cd ..

# 8. Crear link de storage
php artisan storage:link

# 9. Crear admin user en tinker
php artisan tinker
# User::create(['name' => 'Admin', 'email' => 'admin@ileben.com', 'password' => Hash::make('password')])
```

## 📚 Documentación Específica

- [Pagos & Pasarelas](PAYMENTS.md) - Sistema de pagos completo
- [Salesforce Integration](app/Services/Salesforce/README.md) - Integración CRM
- [Filament Resources](app/Filament/Resources/README.md) - Admin resources

## 🔧 Tareas Comunes

### Sincronizar plantas desde Salesforce
```bash
php artisan app:sync-plants
```

### Limpiar caché y compilar
```bash
php artisan optimize:clear
cd frontend && npm run build && cd ..
```

### Activar modo mantenimiento
```bash
php artisan tinker
# SiteSetting::set('maintenance_mode', true);
# SiteSetting::set('maintenance_message', '<h1>Estamos en mantenimiento</h1>');
```

### Compilar frontend en desarrollo
```bash
cd frontend
npm run dev    # Watch mode
npm run build  # Producción
```

### Servir aplicación en desarrollo
```bash
php artisan serve
# Frontend: http://localhost:5173
# Panel Filament: http://localhost:8000/admin
```

## 🗄️ Base de Datos

### Tablas Principales
- `users` - Usuarios del sistema
- `site_settings` - Configuración global (ID = 1)
- `curator` - Archivos/media centralizados
- `payments` - Transacciones de pago
- `projects` - Proyectos disponibles
- `plants` - Catálogo de plantas

### Relaciones
```
User → has many Payments
Payment → belongs to User
SiteSetting → belongs to Media (1:1 vía logo_id, favicon_id, etc)
Project → has Transbank commerce code
```

## 🔐 Seguridad

- ✅ CSRF protection (VerifyCsrfToken)
- ✅ Rate limiting en API
- ✅ Sanctum tokens para API
- ✅ Validation en todos los forms
- ✅ Signature verification en webhooks
- ✅ HTML sanitization en RichEditor
- ✅ Idempotent payment webhooks

## 📊 Monitoreo

### Logs
```bash
tail -f storage/logs/laravel.log
```

### Database Queries
```bash
php artisan tinker
# DB::listen(fn($query) => dump($query->sql, $query->bindings));
```

## 🎨 Customización

### Agregar nuevo tema Web Awesome
1. Editar `resources/css/filament/admin/theme.css`
2. Tema disponible en Configuración → Colores

### Agregar nueva payment gateway
1. Crear `app/Services/Payment/NuevaGatewayService.php`
2. Implementar `PaymentGatewayInterface`
3. Registrar en `PaymentGatewayManager::class`

### Agregar nueva Filament Resource
```bash
php artisan make:filament-resource NombreRecurso --generate
```

## 📝 Convenciones

- Models: Singular, PascalCase (User, Payment)
- Tables: Plural, snake_case (users, payments)
- Fields: snake_case (first_name, user_id)
- Enums: PascalCase (PaymentGateway, PaymentStatus)
- Services: `Service` suffix (PaymentService)

## 🤝 Contribuciones

Este proyecto sigue:
- [Laravel Boost Guidelines](AGENTS.md)
- [Copilot Instructions](.github/copilot-instructions.md)
- [Skills](.github/skills/)

## 📄 Licencia

Todos los derechos reservados - iLeben © 2026

---

**Última actualización:** 25 Feb 2026  
**Versión:** 1.0.0
