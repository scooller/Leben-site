<?php

namespace App\Filament\Pages;

use AlizHarb\ActivityLog\Widgets\ActivityChartWidget;
use AlizHarb\ActivityLog\Widgets\LatestActivityWidget;
use App\Filament\Widgets\ApiMonitoringWidget;
use App\Filament\Widgets\ApiUsageChartWidget;
use App\Filament\Widgets\PaymentGatewayChartWidget;
use App\Filament\Widgets\PaymentsChartWidget;
use App\Filament\Widgets\PaymentStatusChartWidget;
use App\Filament\Widgets\SyncPlantsWidget;
use App\Filament\Widgets\SyncProjectsWidget;
use App\Filament\Widgets\UsersChartWidget;
use App\Models\Proyecto;
use App\Models\SiteSetting;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Components\Forms\RichEditor\AttachCuratorMediaPlugin;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * @return array<string, string>
     */
    protected static function projectOptions(): array
    {
        return Proyecto::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('comuna')
            ->get(['name', 'comuna'])
            ->mapWithKeys(static function (Proyecto $proyecto): array {
                $name = trim((string) $proyecto->name);
                $comuna = trim((string) ($proyecto->comuna ?? ''));

                if ($name === '') {
                    return [];
                }

                $label = $comuna !== '' ? "{$name} ({$comuna})" : $name;

                return [$name => $label];
            })
            ->toArray();
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración del Sitio';

    protected static ?string $title = 'Configuración del Sitio';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.site-settings';

    /**
     * Opciones de widgets disponibles para ordenar en el dashboard.
     */
    protected static function dashboardWidgetOptions(): array
    {
        return [
            ApiMonitoringWidget::class => 'API (Monitoreo)',
            ApiUsageChartWidget::class => 'API (Uso 7 días)',
            UsersChartWidget::class => 'Usuarios (Gráfico)',
            ActivityChartWidget::class => 'Actividad (Gráfico)',
            LatestActivityWidget::class => 'Actividad (Últimos)',
            PaymentsChartWidget::class => 'Pagos (Gráfico)',
            PaymentGatewayChartWidget::class => 'Pagos (Tipo)',
            PaymentStatusChartWidget::class => 'Pagos (Estado)',
            SyncPlantsWidget::class => 'Sync Plantas',
            SyncProjectsWidget::class => 'Sync Proyectos',
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $settings = SiteSetting::current();
        $this->form->fill($settings->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make('Información Básica')
                                    ->schema([
                                        TextInput::make('site_name')
                                            ->label('Nombre del Sitio')
                                            ->required()
                                            ->maxLength(255),

                                        Textarea::make('site_description')
                                            ->label('Descripción')
                                            ->rows(3)
                                            ->maxLength(500),

                                        TextInput::make('site_url')
                                            ->label('URL del Sitio General')
                                            ->url()
                                            ->placeholder('https://ileben.cl'),

                                        Toggle::make('evento_sale')
                                            ->label('Evento Sale')
                                            ->live()
                                            ->helperText('Cuando está activo, el frontend solo mostrará plantas con Porcentaje Máximo de Unidad y usará la etiqueta "Precio Sale".'),

                                        Toggle::make('mostrar_plantas')
                                            ->label('Mostrar plantas')
                                            ->default(true)
                                            ->helperText('Cuando está desactivado, el frontend mostrará un mensaje de "Próximamente" en lugar del catálogo.'),

                                        TextInput::make('extra_settings.catalogo_no_disponible_titulo')
                                            ->label('Título cuando no se muestran plantas')
                                            ->default('Próximamente')
                                            ->maxLength(120)
                                            ->helperText('Título que verá el usuario cuando "Mostrar plantas" esté desactivado.'),

                                        RichEditor::make('extra_settings.catalogo_no_disponible_mensaje')
                                            ->label('Mensaje cuando no se muestran plantas')
                                            ->default('<p>El catálogo de plantas no está disponible por el momento.</p>')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'blockquote',
                                                'h2',
                                                'h3',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                                'redo',
                                                'undo',
                                            ])
                                            ->helperText('Texto visible en el frontend cuando "Mostrar plantas" esté desactivado (acepta formato).'),

                                        CuratorPicker::make('logo_sale_id')
                                            ->label('Logo Sale')
                                            ->helperText('Logo usado en el grid y en el detalle de planta cuando Evento Sale está activo.')
                                            ->visible(fn (Get $get): bool => (bool) $get('evento_sale')),

                                        Repeater::make('footer_menu')
                                            ->label('Menú del Footer')
                                            ->schema([
                                                TextInput::make('label')
                                                    ->label('Texto')
                                                    ->required()
                                                    ->maxLength(100),

                                                TextInput::make('url')
                                                    ->label('URL')
                                                    ->required()
                                                    ->placeholder('https://... o /ruta-interna')
                                                    ->maxLength(2048),

                                                Toggle::make('new_tab')
                                                    ->label('Abrir en nueva pestaña')
                                                    ->default(false),
                                            ])
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->collapsible()
                                            ->columns(2)
                                            ->helperText('Este menú se muestra en el footer del frontend.'),

                                        RichEditor::make('footer_legal_text')
                                            ->label('Texto Legal del Footer')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'blockquote',
                                                'h2',
                                                'h3',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                                'redo',
                                                'undo',
                                            ])
                                            ->helperText('Texto legal visible al final del sitio (acepta formato).')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Banner')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                Section::make('Hero del Home')
                                    ->description('Define el banner principal del home. Puede ser imagen o video, con versión desktop y mobile.')
                                    ->schema([
                                        Select::make('extra_settings.home_hero_type')
                                            ->label('Tipo de banner del Home')
                                            ->options([
                                                'video' => 'Video',
                                                'image' => 'Imagen',
                                            ])
                                            ->default('video')
                                            ->required()
                                            ->live(),

                                        CuratorPicker::make('extra_settings.home_hero_image_desktop_id')
                                            ->label('Imagen Desktop Hero Home')
                                            ->helperText('Se usará en pantallas grandes cuando el tipo sea Imagen.')
                                            ->visible(fn (Get $get): bool => ($get('extra_settings.home_hero_type') ?? 'video') === 'image'),

                                        CuratorPicker::make('extra_settings.home_hero_image_mobile_id')
                                            ->label('Imagen Mobile Hero Home')
                                            ->helperText('Se usará en pantallas pequeñas cuando el tipo sea Imagen.')
                                            ->visible(fn (Get $get): bool => ($get('extra_settings.home_hero_type') ?? 'video') === 'image'),

                                        TextInput::make('extra_settings.home_hero_video_desktop_url')
                                            ->label('URL Video Desktop')
                                            ->url()
                                            ->placeholder('https://.../banner-desktop.mp4')
                                            ->visible(fn (Get $get): bool => ($get('extra_settings.home_hero_type') ?? 'video') === 'video'),

                                        TextInput::make('extra_settings.home_hero_video_mobile_url')
                                            ->label('URL Video Mobile')
                                            ->url()
                                            ->placeholder('https://.../banner-mobile.mp4')
                                            ->visible(fn (Get $get): bool => ($get('extra_settings.home_hero_type') ?? 'video') === 'video'),

                                        CuratorPicker::make('extra_settings.home_hero_video_poster_id')
                                            ->label('Poster del video Hero Home')
                                            ->helperText('Imagen usada mientras carga el video o si no puede reproducirse.')
                                            ->visible(fn (Get $get): bool => ($get('extra_settings.home_hero_type') ?? 'video') === 'video'),
                                    ])
                                    ->columns(1),

                                Section::make('Hero de Contacto')
                                    ->description('Configura las imágenes del hero en la página de contacto para desktop y mobile.')
                                    ->schema([
                                        CuratorPicker::make('extra_settings.contact_hero_image_desktop_id')
                                            ->label('Imagen Desktop Hero Contacto')
                                            ->helperText('Tamaño recomendado: 1920x600px o proporcional.'),

                                        CuratorPicker::make('extra_settings.contact_hero_image_mobile_id')
                                            ->label('Imagen Mobile Hero Contacto')
                                            ->helperText('Tamaño recomendado: 1080x1350px o proporcional.'),

                                        TextInput::make('extra_settings.contact_hero_alt')
                                            ->label('Texto disclaimer para hero de contacto')
                                            ->placeholder('Ejemplo: "Porcentaje corresponde a la unidad X"')
                                            ->maxLength(255),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Dashboard')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Section::make('Orden de Widgets')
                                    ->description('Ordena los widgets del dashboard arrastrando las opciones')
                                    ->schema([
                                        Select::make('dashboard_widget_order')
                                            ->label('Widgets del Dashboard')
                                            ->options(self::dashboardWidgetOptions())
                                            ->multiple()
                                            ->reorderable()
                                            ->searchable()
                                            ->default(array_keys(self::dashboardWidgetOptions()))
                                            ->helperText('Arrastra para reordenar. El orden se aplica al dashboard principal. Para ocultar, elimina el widget del selector.'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Logos e Íconos')
                                    ->description('Sube las imágenes de tu marca')
                                    ->schema([
                                        CuratorPicker::make('logo_id')
                                            ->label('Logo Principal')
                                            ->helperText('Recomendado: PNG con fondo transparente'),

                                        CuratorPicker::make('logo_dark_id')
                                            ->label('Logo Modo Oscuro')
                                            ->helperText('Versión del logo para fondos oscuros'),

                                        CuratorPicker::make('icon_id')
                                            ->label('Ícono/Isotipo')
                                            ->helperText('Ícono cuadrado, mínimo 512x512px'),

                                        CuratorPicker::make('favicon_id')
                                            ->label('Favicon')
                                            ->helperText('ICO o PNG, 32x32px o 64x64px'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Colores')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                Section::make('Configuración de Tema y Paleta')
                                    ->description('Los temas y paletas definen el estilo base del sitio')
                                    ->schema([
                                        Select::make('webawesome_theme')
                                            ->label('Tema Web Awesome')
                                            ->options([
                                                'default' => 'Default',
                                                'awesome' => 'Awesome',
                                                'shoelace' => 'Shoelace',
                                                'active' => 'Active',
                                                'brutalist' => 'Brutalist',
                                                'glossy' => 'Glossy',
                                                'matter' => 'Matter',
                                                'mellow' => 'Mellow',
                                                'playful' => 'Playful',
                                                'premium' => 'Premium',
                                                'tailspin' => 'Tailspin',
                                            ])
                                            ->helperText('Define el estilo base y colores del sitio')
                                            ->required(),

                                        Select::make('webawesome_palette')
                                            ->label('Paleta de Colores')
                                            ->options([
                                                'default' => 'Default',
                                                'bright' => 'Bright',
                                                'shoelace' => 'Shoelace',
                                                'rudimentary' => 'Rudimentary (Pro)',
                                                'elegant' => 'Elegant (Pro)',
                                                'mild' => 'Mild (Pro)',
                                                'natural' => 'Natural (Pro)',
                                                'anodized' => 'Anodized (Pro)',
                                                'vogue' => 'Vogue (Pro)',
                                            ])
                                            ->helperText('Define los tonos y matices específicos de los colores')
                                            ->required(),

                                        // agregar color principal de la marca para aplicar a botones, enlaces y elementos destacados
                                        ColorPicker::make('brand_color')
                                            ->label('Color Principal de la Marca')
                                            ->default('#eb0029')
                                            ->required()
                                            ->helperText('Color principal de tu marca, aplicado a botones, enlaces y elementos destacados'),
                                    ])
                                    ->columns(2),

                                Section::make('Colores Semánticos')
                                    ->description('Selecciona el color específico para cada grupo semántico. Esto aplicará clases CSS como wa-brand-blue, wa-success-green, etc.')
                                    ->schema([
                                        Select::make('semantic_brand_color')
                                            ->label('Color Brand (Marca)')
                                            ->options([
                                                'brand_color' => 'Color principal',
                                                'red' => 'Red',
                                                'orange' => 'Orange',
                                                'yellow' => 'Yellow',
                                                'green' => 'Green',
                                                'cyan' => 'Cyan',
                                                'blue' => 'Blue',
                                                'indigo' => 'Indigo',
                                                'purple' => 'Purple',
                                                'pink' => 'Pink',
                                                'gray' => 'Gray',
                                            ])
                                            ->default('blue')
                                            ->required()
                                            ->helperText('Si eliges "Color principal", se usa el valor de "Color Principal de la Marca"'),

                                        Select::make('semantic_neutral_color')
                                            ->label('Color Neutral')
                                            ->options([
                                                'red' => 'Red',
                                                'orange' => 'Orange',
                                                'yellow' => 'Yellow',
                                                'green' => 'Green',
                                                'cyan' => 'Cyan',
                                                'blue' => 'Blue',
                                                'indigo' => 'Indigo',
                                                'purple' => 'Purple',
                                                'pink' => 'Pink',
                                                'gray' => 'Gray',
                                            ])
                                            ->default('gray')
                                            ->required()
                                            ->helperText('Color para elementos neutrales'),

                                        Select::make('semantic_success_color')
                                            ->label('Color Success (Éxito)')
                                            ->options([
                                                'red' => 'Red',
                                                'orange' => 'Orange',
                                                'yellow' => 'Yellow',
                                                'green' => 'Green',
                                                'cyan' => 'Cyan',
                                                'blue' => 'Blue',
                                                'indigo' => 'Indigo',
                                                'purple' => 'Purple',
                                                'pink' => 'Pink',
                                                'gray' => 'Gray',
                                            ])
                                            ->default('green')
                                            ->required()
                                            ->helperText('Color para mensajes de éxito'),

                                        Select::make('semantic_warning_color')
                                            ->label('Color Warning (Advertencia)')
                                            ->options([
                                                'red' => 'Red',
                                                'orange' => 'Orange',
                                                'yellow' => 'Yellow',
                                                'green' => 'Green',
                                                'cyan' => 'Cyan',
                                                'blue' => 'Blue',
                                                'indigo' => 'Indigo',
                                                'purple' => 'Purple',
                                                'pink' => 'Pink',
                                                'gray' => 'Gray',
                                            ])
                                            ->default('yellow')
                                            ->required()
                                            ->helperText('Color para advertencias'),

                                        Select::make('semantic_danger_color')
                                            ->label('Color Danger (Peligro)')
                                            ->options([
                                                'red' => 'Red',
                                                'orange' => 'Orange',
                                                'yellow' => 'Yellow',
                                                'green' => 'Green',
                                                'cyan' => 'Cyan',
                                                'blue' => 'Blue',
                                                'indigo' => 'Indigo',
                                                'purple' => 'Purple',
                                                'pink' => 'Pink',
                                                'gray' => 'Gray',
                                            ])
                                            ->default('red')
                                            ->required()
                                            ->helperText('Color para errores y peligros'),
                                    ])
                                    ->columns(2),

                                Section::make('Familia de Iconos')
                                    ->description('Selecciona el estilo de los iconos de Font Awesome / Web Awesome')
                                    ->schema([
                                        Select::make('icon_family')
                                            ->label('Familia de Iconos')
                                            ->options([
                                                'classic' => 'Classic',
                                                'sharp' => 'Sharp',
                                                'duotone' => 'Duotone',
                                                'sharp-duotone' => 'Sharp Duotone',
                                            ])
                                            ->default('classic')
                                            ->required()
                                            ->helperText('Define el estilo visual de los iconos (se aplica mediante data-font-family en el HTML)'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Tipografía')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Google Fonts')
                                    ->description('Pega la URL del stylesheet de Google Fonts para cargar las fuentes automáticamente')
                                    ->schema([
                                        Textarea::make('google_fonts_stylesheet')
                                            ->label('URL del Stylesheet de Google Fonts')
                                            ->placeholder('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap')
                                            ->rows(3)
                                            ->helperText('Copia la URL completa desde Google Fonts. Esto cargará las fuentes con todos sus pesos y variantes.')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),

                                Section::make('Configuración de Fuentes')
                                    ->description('Especifica los nombres de las fuentes a usar en el sitio')
                                    ->schema([
                                        TextInput::make('font_family_body')
                                            ->label('Fuente del Cuerpo')
                                            ->placeholder('Ej: "Inter", sans-serif')
                                            ->helperText('Fuente para el texto general. Mapea a --wa-font-family-body'),

                                        TextInput::make('font_family_heading')
                                            ->label('Fuente de Encabezados')
                                            ->placeholder('Ej: "Poppins", sans-serif')
                                            ->helperText('Fuente para títulos y encabezados. Mapea a --wa-font-family-heading'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Section::make('Optimización para Motores de Búsqueda')
                                    ->schema([
                                        Textarea::make('meta_keywords')
                                            ->label('Palabras Clave (Keywords)')
                                            ->rows(2)
                                            ->helperText('Separadas por comas'),

                                        TextInput::make('meta_author')
                                            ->label('Autor'),

                                        TextInput::make('tag_manager_id')
                                            ->label('Google Tag Manager ID')
                                            ->placeholder('GTM-XXXXXXX')
                                            ->maxLength(50)
                                            ->helperText('ID del contenedor de Google Tag Manager para cargar el script y eventos del frontend.'),

                                        TextInput::make('extra_settings.default_meta_title')
                                            ->label('Título SEO por defecto')
                                            ->maxLength(120)
                                            ->helperText('Fallback para el <title> cuando una página no define título propio.'),

                                        TextInput::make('extra_settings.default_og_title')
                                            ->label('Open Graph título por defecto')
                                            ->maxLength(120)
                                            ->helperText('Título base para compartir en redes sociales.'),

                                        Textarea::make('extra_settings.default_og_description')
                                            ->label('Open Graph descripción por defecto')
                                            ->rows(2)
                                            ->maxLength(300)
                                            ->helperText('Descripción base para compartir en redes sociales.'),

                                        TextInput::make('extra_settings.twitter_site')
                                            ->label('Twitter / X @usuario del sitio')
                                            ->maxLength(50)
                                            ->placeholder('@ileben')
                                            ->helperText('Cuenta oficial del sitio para Twitter Cards (opcional).'),

                                        Select::make('extra_settings.robots_default')
                                            ->label('Meta robots por defecto')
                                            ->options([
                                                'index,follow' => 'index,follow',
                                                'noindex,follow' => 'noindex,follow',
                                                'noindex,nofollow' => 'noindex,nofollow',
                                            ])
                                            ->default('index,follow')
                                            ->helperText('Directiva robots por defecto para páginas públicas.'),

                                        Select::make('extra_settings.site_locale')
                                            ->label('Locale del sitio para Open Graph')
                                            ->options([
                                                'es-CL' => 'es-CL (Chile)',
                                                'es-419' => 'es-419 (LatAm)',
                                                'es-ES' => 'es-ES (España)',
                                            ])
                                            ->default('es-CL')
                                            ->helperText('Se usa como locale base en metadatos sociales.'),

                                        TextInput::make('extra_settings.utm_campaign_default')
                                            ->label('UTM Campaign por defecto')
                                            ->default('campaign')
                                            ->maxLength(100)
                                            ->helperText('Valor por defecto para utm_campaign cuando no llega en la URL (ej: campaign).'),

                                        FileUpload::make('og_image')
                                            ->label('Imagen Open Graph')
                                            ->image()
                                            ->directory('seo')
                                            ->visibility('public')
                                            ->helperText('Imagen para compartir en redes sociales (1200x630px)'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Contacto')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('Información de Contacto')
                                    ->schema([
                                        TextInput::make('contact_email')
                                            ->label('Email de Contacto')
                                            ->email(),

                                        TextInput::make('contact_phone')
                                            ->label('Teléfono')
                                            ->tel(),

                                        Textarea::make('contact_address')
                                            ->label('Dirección')
                                            ->rows(3),
                                    ])
                                    ->columns(1),

                                Section::make('Página de Contacto')
                                    ->description('Contenido administrable para la vista de contacto en el frontend')
                                    ->schema([
                                        TextInput::make('contact_page_title')
                                            ->label('Título')
                                            ->maxLength(255)
                                            ->placeholder('Contacto'),

                                        Textarea::make('contact_page_subtitle')
                                            ->label('Subtítulo')
                                            ->rows(2)
                                            ->maxLength(500),

                                        RichEditor::make('contact_page_content')
                                            ->label('Contenido')
                                            ->toolbarButtons([
                                                'bold',
                                                'italic',
                                                'underline',
                                                'strike',
                                                'blockquote',
                                                'h2',
                                                'h3',
                                                'bulletList',
                                                'orderedList',
                                                'link',
                                                'redo',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),

                                        TextInput::make('contact_notification_email')
                                            ->label('Email receptor de contactos')
                                            ->email()
                                            ->placeholder('ventas@tuempresa.cl')
                                            ->helperText('Si lo dejas vacío, se utilizará el Email de Contacto de esta misma pestaña.'),

                                        Repeater::make('contact_form_fields')
                                            ->label('Campos del formulario de contacto')
                                            ->schema([
                                                TextInput::make('key')
                                                    ->label('Clave interna')
                                                    ->required()
                                                    ->maxLength(50)
                                                    ->helperText('Ej: name, rut, email, reason, message'),

                                                TextInput::make('label')
                                                    ->label('Etiqueta')
                                                    ->required()
                                                    ->maxLength(100),

                                                TextInput::make('icon')
                                                    ->label('Ícono')
                                                    ->maxLength(100)
                                                    ->placeholder('Ej: envelope, phone, map-location')
                                                    ->helperText('Nombre del ícono de Web Awesome que se mostrará en el campo.'),

                                                Select::make('type')
                                                    ->label('Tipo')
                                                    ->options([
                                                        'text' => 'Texto',
                                                        'email' => 'Email',
                                                        'tel' => 'Teléfono',
                                                        'number' => 'Número',
                                                        'textarea' => 'Área de texto',
                                                        'rut' => 'RUT',
                                                        'select' => 'Selector',
                                                    ])
                                                    ->required()
                                                    ->default('text')
                                                    ->live(),

                                                Select::make('projects')
                                                    ->label('Mostrar para proyecto')
                                                    ->options(self::projectOptions())
                                                    ->multiple()
                                                    ->searchable()
                                                    ->preload()
                                                    ->visible(fn (Get $get): bool => $get('type') !== 'select')
                                                    ->helperText('Opcional. Si seleccionas proyectos, este campo solo se mostrará cuando el proyecto seleccionado pertenezca a alguno de ellos.'),

                                                TextInput::make('placeholder')
                                                    ->label('Placeholder')
                                                    ->maxLength(255),

                                                Repeater::make('options')
                                                    ->label('Opciones del selector')
                                                    ->schema([
                                                        TextInput::make('label')
                                                            ->label('Etiqueta')
                                                            ->required()
                                                            ->maxLength(100),

                                                        TextInput::make('value')
                                                            ->label('Valor')
                                                            ->required()
                                                            ->maxLength(100),

                                                        Select::make('projects')
                                                            ->label('Mostrar para proyecto')
                                                            ->options(self::projectOptions())
                                                            ->multiple()
                                                            ->searchable()
                                                            ->preload()
                                                            ->columnSpanFull()
                                                            ->helperText('Opcional. Si seleccionas proyectos, esta opción solo se usará para esos proyectos.'),
                                                    ])
                                                    ->visible(fn (Get $get): bool => $get('type') === 'select')
                                                    ->defaultItems(0)
                                                    ->reorderable()
                                                    ->collapsible()
                                                    ->columns(2)
                                                    ->columnSpanFull()
                                                    ->helperText('Estas opciones estarán disponibles en el frontend cuando el tipo sea Selector.'),

                                                Toggle::make('required')
                                                    ->label('Obligatorio')
                                                    ->default(false),
                                            ])
                                            ->defaultItems(0)
                                            ->reorderable()
                                            ->collapsible()
                                            ->columns(2)
                                            ->helperText('Puedes definir cuántos campos deseas mostrar y validar en el formulario, incluyendo RUT y selectores.'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Redes Sociales')
                            ->icon('heroicon-o-share')
                            ->schema([
                                Section::make('Enlaces a Redes Sociales')
                                    ->schema([
                                        TextInput::make('facebook_url')
                                            ->label('Facebook')
                                            ->url()
                                            ->placeholder('https://facebook.com/tupagina'),

                                        TextInput::make('instagram_url')
                                            ->label('Instagram')
                                            ->url()
                                            ->placeholder('https://instagram.com/tuusuario'),

                                        TextInput::make('twitter_url')
                                            ->label('Twitter / X')
                                            ->url()
                                            ->placeholder('https://twitter.com/tuusuario'),

                                        TextInput::make('linkedin_url')
                                            ->label('LinkedIn')
                                            ->url()
                                            ->placeholder('https://linkedin.com/company/tuempresa'),

                                        TextInput::make('youtube_url')
                                            ->label('YouTube')
                                            ->url()
                                            ->placeholder('https://youtube.com/@tucanal'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Personalización')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Section::make('CSS Personalizado')
                                    ->schema([
                                        Textarea::make('custom_css')
                                            ->label('CSS Adicional')
                                            ->rows(10)
                                            ->helperText('CSS que se inyectará en el frontend'),
                                    ]),

                                Section::make('Scripts Adicionales')
                                    ->schema([
                                        Textarea::make('header_scripts')
                                            ->label('Scripts en Header')
                                            ->rows(5)
                                            ->helperText('Scripts que se insertarán en <head> (ej: Google Analytics)'),

                                        Textarea::make('footer_scripts')
                                            ->label('Scripts en Footer')
                                            ->rows(5)
                                            ->helperText('Scripts que se insertarán antes de </body>'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Pasarelas de Pago')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Section::make('Métodos de Pago Disponibles')
                                    ->description('Activa o desactiva los métodos de pago disponibles para los clientes')
                                    ->schema([
                                        Toggle::make('gateway_transbank_enabled')
                                            ->label('Transbank (Webpay)')
                                            ->helperText('Tarjetas de débito y crédito chilenas')
                                            ->default(true),

                                        Toggle::make('gateway_mercadopago_enabled')
                                            ->label('Mercado Pago')
                                            ->helperText('Tarjetas y otros métodos latinoamericanos')
                                            ->default(false),

                                        Toggle::make('gateway_manual_enabled')
                                            ->label('Pago Manual')
                                            ->helperText('Transferencia bancaria, efectivo u otro método offline')
                                            ->default(true),

                                        TextInput::make('gateway_manual_config.auto_expire_minutes')
                                            ->label('Tiempo de reserva en Pago Manual (minutos)')
                                            ->helperText('Cuánto tiempo se mantiene la reserva cuando se inicia un pago manual. Si está vacío se usa la configuración por defecto del gateway manual.')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(10080)
                                            ->step(1)
                                            ->nullable(),

                                        TextInput::make('gateway_reservation_timeout_minutes')
                                            ->label('Tiempo de espera de reserva (minutos)')
                                            ->helperText('Tiempo máximo que una planta queda reservada antes de liberarse automáticamente.')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(120)
                                            ->step(1)
                                            ->default(15)
                                            ->required(),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Salesforce')
                            ->icon('heroicon-o-arrow-path')
                            ->schema([
                                Section::make('Sincronización Automática de Plantas')
                                    ->description('Configura cada cuánto se sincronizan automáticamente las plantas y qué tipos se incluirán.')
                                    ->schema([
                                        TextInput::make('salesforce_sync_interval_minutes')
                                            ->label('Frecuencia de sincronización automática (minutos)')
                                            ->helperText('Define cada cuántos minutos se debe ejecutar la sincronización automática de plantas desde Salesforce.')
                                            ->numeric()
                                            ->minValue(5)
                                            ->step(5)
                                            ->default(1440)
                                            ->required(),

                                        Select::make('salesforce_sync_plant_types')
                                            ->label('Tipos de planta a sincronizar')
                                            ->helperText('Selecciona los tipos de planta que se deben considerar en la sincronización automática.')
                                            ->options([
                                                'ESTACIONAMIENTO' => 'ESTACIONAMIENTO',
                                                'DEPARTAMENTO' => 'DEPARTAMENTO',
                                                'BODEGA' => 'BODEGA',
                                                'LOCAL' => 'LOCAL',
                                            ])
                                            ->multiple()
                                            ->searchable()
                                            ->default(['ESTACIONAMIENTO', 'DEPARTAMENTO', 'BODEGA', 'LOCAL'])
                                            ->required(),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Mantenimiento')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Section::make('Modo de Mantenimiento')
                                    ->description('Activa el modo de mantenimiento para realizar actualizaciones')
                                    ->schema([
                                        Toggle::make('maintenance_mode')
                                            ->label('Activar Modo de Mantenimiento')
                                            ->helperText('El sitio mostrará un mensaje de mantenimiento')
                                            ->live(),

                                        Toggle::make('maintenance_use_html')
                                            ->label('Editar como HTML')
                                            ->helperText('Activa para editar el mensaje directamente en HTML')
                                            ->default(false)
                                            ->live()
                                            ->visible(fn ($get) => $get('maintenance_mode')),

                                        RichEditor::make('maintenance_message')
                                            ->label('Mensaje de Mantenimiento')
                                            ->default('Estamos realizando mejoras. Volveremos pronto.')
                                            ->helperText('Usa el botón 📎 (Attach Curator Media) en la barra para insertar imágenes del File Manager. Puedes usar formato, negritas, listas y enlaces.')
                                            ->toolbarButtons([
                                                'attachCuratorMedia',
                                                'blockquote',
                                                'bold',
                                                'bulletList',
                                                'codeBlock',
                                                'h2',
                                                'h3',
                                                'italic',
                                                'link',
                                                'orderedList',
                                                'redo',
                                                'strike',
                                                'undo',
                                            ])
                                            ->plugins([
                                                AttachCuratorMediaPlugin::make(),
                                            ])
                                            ->visible(fn ($get) => $get('maintenance_mode') && ! $get('maintenance_use_html'))
                                            ->dehydrated(fn ($get) => ! $get('maintenance_use_html'))
                                            ->columnSpanFull(),

                                        Textarea::make('maintenance_message_html')
                                            ->label('Mensaje de Mantenimiento (HTML)')
                                            ->helperText('Código HTML directo. Puedes usar <img>, <a>, <strong>, <p>, listas, etc.')
                                            ->rows(10)
                                            ->afterStateHydrated(function (Textarea $component, $state, $get) {
                                                // Cargar el valor desde maintenance_message cuando se abre
                                                $message = $get('maintenance_message');
                                                if ($message) {
                                                    $component->state($message);
                                                }
                                            })
                                            ->dehydrateStateUsing(function ($state, $set) {
                                                // Guardar el HTML en maintenance_message
                                                $set('maintenance_message', $state);

                                                return null; // No guardar en maintenance_message_html
                                            })
                                            ->visible(fn ($get) => $get('maintenance_mode') && $get('maintenance_use_html'))
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar Configuración')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            SiteSetting::current()->update($data);

            Notification::make()
                ->success()
                ->title('Configuración guardada')
                ->body('La configuración del sitio se ha actualizado correctamente.')
                ->send();
        } catch (Halt $exception) {
            return;
        }
    }
}
