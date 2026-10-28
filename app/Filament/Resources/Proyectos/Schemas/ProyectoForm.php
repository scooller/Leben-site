<?php

namespace App\Filament\Resources\Proyectos\Schemas;

use App\Models\Proyecto;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProyectoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->model(Proyecto::class)
            ->schema([
                Tabs::make('ProyectoTabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Información General & Salesforce')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Información General')
                                    ->description('Datos básicos del proyecto')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre del Proyecto')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->required(),

                                        TextInput::make('salesforce_id')
                                            ->label('Salesforce Product ID')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->suffixAction(
                                                Action::make('openSalesforceProject')
                                                    ->label('Ver en Salesforce')
                                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                                    ->url(fn (?Proyecto $record): ?string => filled($record?->salesforce_id)
                                                        ? "https://leben.lightning.force.com/lightning/r/Proyecto__c/{$record->salesforce_id}/view"
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_id))
                                            ),

                                        Textarea::make('descripcion')
                                            ->label('Descripción')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->rows(3),

                                        Select::make('tipo')
                                            ->label('Tipo de Proyecto')
                                            ->beforeLabel(Icon::make(Heroicon::BuildingOffice))
                                            ->multiple()
                                            ->options([
                                                'best' => 'Best',
                                                'broker' => 'Broker',
                                                'home' => 'Home',
                                                'icon' => 'Icon',
                                                'invest' => 'Invest',
                                            ])
                                            ->searchable()
                                            ->preload(),

                                        TextInput::make('direccion')
                                            ->label('Dirección')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('comuna')
                                            ->label('Comuna')
                                            ->maxLength(255),

                                        TextInput::make('provincia')
                                            ->label('Provincia')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('region')
                                            ->label('Región')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('razon_social')
                                            ->label('Razón Social')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('rut')
                                            ->label('RUT')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('telefono')
                                            ->label('Teléfono')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('pagina_web')
                                            ->label('Página Web')
                                            ->url()
                                            ->placeholder('https://...')
                                            ->suffixAction(
                                                Action::make('openProjectWebsite')
                                                    ->label('Ver página')
                                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                                    ->url(fn (?Proyecto $record): ?string => filled($record?->pagina_web)
                                                        ? (str_starts_with($record->pagina_web, 'http://') || str_starts_with($record->pagina_web, 'https://')
                                                            ? $record->pagina_web
                                                            : "https://{$record->pagina_web}")
                                                        : null)
                                                    ->openUrlInNewTab()
                                                    ->visible(fn (?Proyecto $record): bool => filled($record?->pagina_web))
                                            ),

                                        DatePicker::make('fecha_inicio_ventas')
                                            ->label('Fecha Inicio Ventas')
                                            ->disabled()
                                            ->dehydrated(false),

                                        TextInput::make('fecha_entrega')
                                            ->label('Fecha de Entrega')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Select::make('etapa')
                                            ->options(Proyecto::etapaOptions())
                                            ->nullable()
                                            ->placeholder('--None--')
                                            ->label('Etapa'),

                                        TextInput::make('horario_atencion')
                                            ->label('Horario de Atención')
                                            ->disabled()
                                            ->dehydrated(false),

                                        Toggle::make('is_active')
                                            ->label('Activo')
                                            ->helperText('Si el proyecto no está activo, no se mostrará en la página web')
                                            ->required(),

                                        TextInput::make('sort_order')
                                            ->label('Orden de Visualización')
                                            ->numeric()
                                            ->default(0)
                                            ->helperText('Posición en el catálogo público y APIs'),

                                        Select::make('asesores')
                                            ->label('Asesores')
                                            ->beforeLabel(Icon::make(Heroicon::UserGroup))
                                            ->relationship(
                                                name: 'asesores',
                                                titleAttribute: 'email',
                                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                                    ->orderBy('first_name')
                                                    ->orderBy('last_name')
                                            )
                                            ->multiple()
                                            ->preload()
                                            ->searchable(['first_name', 'last_name', 'email'])
                                            ->getOptionLabelFromRecordUsing(function (Model $record): string {
                                                $fullName = trim(implode(' ', array_filter([$record->first_name, $record->last_name])));

                                                if ($fullName !== '' && filled($record->email)) {
                                                    return "{$fullName} ({$record->email})";
                                                }

                                                return $fullName !== '' ? $fullName : (string) ($record->email ?? 'Asesor');
                                            })
                                            ->helperText('Puedes vincular uno o más asesores a este proyecto. Los asesores Salesforce se sincronizan automáticamente al sincronizar proyectos.')
                                            ->columnSpan(2),

                                        CuratorPicker::make('project_image_id')
                                            ->label('Imagen del Proyecto')
                                            ->helperText('Imagen manual del proyecto. Si no se define, se usará la Portada de Salesforce; si tampoco existe, se usará el logo principal del sitio o un ícono por defecto.')
                                            ->columnSpan(2),

                                        Section::make('Branding Salesforce')
                                            ->description('Activos sincronizados automáticamente desde Salesforce para este proyecto')
                                            ->schema([
                                                TextInput::make('salesforce_portada_url')
                                                    ->label('Portada (Salesforce)')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->url()
                                                    ->suffixAction(
                                                        Action::make('openSalesforcePortada')
                                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                                            ->url(fn (?Proyecto $record): ?string => $record?->salesforce_portada_url)
                                                            ->openUrlInNewTab()
                                                            ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_portada_url))
                                                    ),

                                                TextInput::make('salesforce_logo_url')
                                                    ->label('Logo (Salesforce)')
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->url()
                                                    ->suffixAction(
                                                        Action::make('openSalesforceLogo')
                                                            ->icon('heroicon-m-arrow-top-right-on-square')
                                                            ->url(fn (?Proyecto $record): ?string => $record?->salesforce_logo_url)
                                                            ->openUrlInNewTab()
                                                            ->visible(fn (?Proyecto $record): bool => filled($record?->salesforce_logo_url))
                                                    ),

                                                Placeholder::make('salesforce_branding_hint')
                                                    ->hiddenLabel()
                                                    ->content('La portada se usa como fallback de "Imagen del Proyecto" cuando no hay imagen manual cargada en Curator.')
                                                    ->columnSpan(2),
                                            ])
                                            ->columns(2)
                                            ->columnSpan(2),
                                    ])
                                    ->columns(2),

                                Section::make('Pago y Financiamiento')
                                    ->description('Configuración de códigos de comercio, pago manual, reserva exigida y entrega inmediata')
                                    ->schema([
                                        Select::make('transbank_commerce_code')
                                            ->label('Código de Comercio Transbank Mall')
                                            ->beforeLabel(Icon::make(Heroicon::DocumentCurrencyDollar))
                                            ->helperText('Código único de comercio para Transbank en este proyecto')
                                            ->options(function () {
                                                $codes = config('payments.gateways.transbank.commerce_codes', []);

                                                if (empty($codes)) {
                                                    return [];
                                                }

                                                // Crear opciones: code => "Nombre del Proyecto - CODE"
                                                $options = [];
                                                foreach ($codes as $slug => $code) {
                                                    // Buscar el proyecto por slug
                                                    $proyecto = Proyecto::where('slug', $slug)->first();

                                                    // Si encuentra proyecto, mostrar nombre + código
                                                    // Si no, mostrar slug + código como fallback
                                                    if ($proyecto) {
                                                        $label = "{$proyecto->name}";
                                                    } else {
                                                        $label = "{$slug}";
                                                    }

                                                    $options[$code] = $label;
                                                }

                                                return $options;
                                            })
                                            ->searchable()
                                            ->nullable()
                                            ->validationMessages([
                                                'in' => 'El código de comercio seleccionado no es válido o no está configurado en Transbank.',
                                            ])
                                            ->columnSpanFull(),

                                        Section::make('Pago Manual por Proyecto')
                                            ->description('Configura los datos de depósito y/o link de pago para este proyecto')
                                            ->schema([
                                                TextInput::make('manual_payment_link')
                                                    ->label('Link de Pago del Proyecto')
                                                    ->url()
                                                    ->placeholder('https://...')
                                                    ->helperText('Si se define, se enviará al checkout manual de este proyecto.'),

                                                Textarea::make('manual_payment_instructions')
                                                    ->label('Instrucciones de Depósito')
                                                    ->rows(3)
                                                    ->maxLength(2000)
                                                    ->helperText('Estas instrucciones tienen prioridad sobre la configuración global.'),

                                                Repeater::make('manual_payment_bank_accounts')
                                                    ->label('Cuentas para Depósito')
                                                    ->schema([
                                                        TextInput::make('bank')
                                                            ->label('Banco')
                                                            ->maxLength(150),
                                                        TextInput::make('account_type')
                                                            ->label('Tipo de Cuenta')
                                                            ->maxLength(100),
                                                        TextInput::make('account_number')
                                                            ->label('Número de Cuenta')
                                                            ->maxLength(100),
                                                        TextInput::make('account_holder')
                                                            ->label('Titular')
                                                            ->maxLength(150),
                                                        TextInput::make('rut')
                                                            ->label('RUT Titular')
                                                            ->maxLength(20),
                                                    ])
                                                    ->columns(2)
                                                    ->defaultItems(0)
                                                    ->reorderable(false)
                                                    ->collapsible()
                                                    ->helperText('Puedes agregar una o más cuentas de depósito para este proyecto.'),
                                            ])
                                            ->columns(1),

                                        Section::make('Descuentos y valores')
                                            ->description('Solo administradores y super administradores pueden modificar los valores de reserva y descuentos.')
                                            ->schema([
                                                TextInput::make('valor_reserva_exigido_defecto_peso')
                                                    ->label('Valor Defecto ($)')
                                                    ->beforeLabel(Icon::make(Heroicon::CurrencyDollar))
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled(fn (): bool => ! (auth()->user()?->canManageFinancialValues() ?? false)),

                                                TextInput::make('valor_reserva_exigido_min_peso')
                                                    ->label('Valor Mínimo ($)')
                                                    ->beforeLabel(Icon::make(Heroicon::CurrencyDollar))
                                                    ->numeric()
                                                    ->prefix('$')
                                                    ->disabled(fn (): bool => ! (auth()->user()?->canManageFinancialValues() ?? false)),

                                                TextInput::make('descuento_defecto_cotizacion_web')
                                                    ->label('Defecto Cotización Web (%)')
                                                    ->beforeLabel(Icon::make(Heroicon::PercentBadge))
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->prefix('Dcto.')
                                                    ->suffix('%')
                                                    ->disabled(fn (): bool => ! (auth()->user()?->canManageFinancialValues() ?? false)),

                                                TextInput::make('descuento_maximo_unidad')
                                                    ->label('Máximo Unidad (%)')
                                                    ->beforeLabel(Icon::make(Heroicon::PercentBadge))
                                                    ->belowContent([
                                                        'Se aplica cuando Sale este Activo',
                                                        Icon::make(Heroicon::InformationCircle),
                                                    ])
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->prefix('Dcto.')
                                                    ->suffix('%')
                                                    ->disabled(fn (): bool => ! (auth()->user()?->canManageFinancialValues() ?? false)),

                                                TextInput::make('descuento_iva')
                                                    ->label('Dcto. IVA')
                                                    ->beforeLabel(Icon::make(Heroicon::PercentBadge))
                                                    ->numeric()
                                                    ->step(0.01)
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->prefix('Dcto.')
                                                    ->suffix('%')
                                                    ->disabled(fn (): bool => ! (auth()->user()?->canManageFinancialValues() ?? false)),
                                            ])
                                            ->columns(2),

                                        Toggle::make('entrega_inmediata')
                                            ->label('Entrega Inmediata')
                                            ->helperText('Si el proyecto se entrega de forma inmediata, se mostrará en la página web con fecha de entrega inmediata')
                                            ->columnSpan(2),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make('Información Web (Ficha Canónica)')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Group::make()
                                    ->relationship('detail')
                                    ->columnSpanFull()
                                    ->schema([
                                        Tabs::make('FichaWebSecciones')
                                            ->vertical()
                                            ->contained(false)
                                            ->persistTabInQueryString('seccion_web')
                                            ->extraAttributes([
                                                'class' => '!flex-row-reverse items-start gap-6 [&>.fi-sc-tabs-tab.fi-active]:!ms-0 [&>.fi-sc-tabs-tab.fi-active]:!mt-0 [&>.fi-sc-tabs-tab.fi-active]:flex-1 [&>.fi-tabs]:w-64 md:[&>.fi-tabs]:w-72 [&>.fi-tabs]:shrink-0 [&>.fi-tabs]:sticky [&>.fi-tabs]:top-20',
                                            ])
                                            ->tabs([
                                                // 1. PALETA & HERO
                                                Tab::make('1. Identidad & Hero')
                                                    ->icon('heroicon-o-sparkles')
                                                    ->schema([
                                                        Section::make('Identidad Visual, Paleta & Hero')
                                                            ->description('Colores de acento CSS, video de cabecera y logotipos')
                                                            ->schema([
                                                                ColorPicker::make('theme_colors.accent')->label('Color de Acento (--lb-project-accent)')->placeholder('#d9bc70'),
                                                                ColorPicker::make('theme_colors.accent_dark')->label('Acento Oscuro (--lb-project-accent-dark)')->placeholder('#c4a355'),
                                                                ColorPicker::make('theme_colors.accent_light')->label('Acento Claro (--lb-project-accent-light)')->placeholder('#e2ca8d'),
                                                                TextInput::make('theme_colors.gradient')->label('Gradiente (--lb-project-gradient)')->placeholder('linear-gradient(135deg, #d9bc70 0%, #b89345 100%)'),
                                                                TextInput::make('hero_eyebrow')->label('Eyebrow Hero')->placeholder('Puerto Varas'),
                                                                TextInput::make('hero_title')->label('Título Hero (SplitTitle)')->placeholder('VIVE EL LUJO EN PUERTO VARAS'),
                                                                TextInput::make('hero_subtitle')->label('Subtítulo Hero')->nullable(),
                                                                TextInput::make('hero_video_url')->label('URL Video Hero (MP4)')->url()->placeholder('https://... o video/inn-new.mp4'),
                                                                CuratorPicker::make('hero_logo_id')->label('Logo Header (Curator)'),
                                                                TextInput::make('hero_logo_url')->label('URL Directa de Logo Header (Fallback)')->placeholder('images/inn/Logo_INN_Header.png'),
                                                                Repeater::make('technical_info')
                                                                    ->label('Barra Técnica de Especificaciones (INFO)')
                                                                    ->schema([
                                                                        TextInput::make('id')->label('ID')->required(),
                                                                        TextInput::make('label')->label('Etiqueta')->required(),
                                                                        TextInput::make('value')->label('Valor (HTML soportado)')->required(),
                                                                    ])
                                                                    ->columns(3)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Toggle::make('announcement.enabled')->label('Activar Aviso Emergente')->default(false)->columnSpan(2),
                                                                TextInput::make('announcement.title')->label('Título del Aviso')->placeholder('Horario Especial'),
                                                                RichEditor::make('announcement.message')->label('Mensaje del Aviso')->columnSpan(2),
                                                                Repeater::make('schedule')
                                                                    ->label('Líneas de Horario de Atención')
                                                                    ->simple(TextInput::make('item')->label('Horario'))
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 2. SECCIÓN PROYECTO & VIDEOS INTERMEDIOS
                                                Tab::make('2. Proyecto & Videos')
                                                    ->icon('heroicon-o-film')
                                                    ->schema([
                                                        Section::make('Sección Proyecto & Videos Intermedios')
                                                            ->description('Textos editoriales del proyecto, oferta destacada, carrusel de fotos y banners de video')
                                                            ->schema([
                                                                TextInput::make('project_eyebrow')->label('Eyebrow Sección')->default('Proyecto'),
                                                                TextInput::make('project_title')->label('Título Sección')->placeholder('LOS MEJORES DEPARTAMENTOS DE PUERTO VARAS'),
                                                                TextInput::make('project_highlight_offer')->label('Oferta Destacada (HTML)')->placeholder('paga el pie en <b>60</b> cuotas'),
                                                                RichEditor::make('project_description')->label('Descripción Editorial (HTML)')->columnSpanFull(),
                                                                Repeater::make('project_slides')
                                                                    ->label('Slides del Carrusel de Proyecto')
                                                                    ->schema([
                                                                        TextInput::make('img')->label('Ruta o URL de Imagen')->required()->placeholder('images/inn/proyecto/Proyecto_01_Acceso.jpg'),
                                                                        TextInput::make('alt')->label('Texto Alternativo')->required()->placeholder('Acceso'),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Repeater::make('video_interstitials')
                                                                    ->label('Banners de Video Intermedios (Entre Secciones)')
                                                                    ->schema([
                                                                        TextInput::make('text')->label('Texto Motivacional sobre Video')->required(),
                                                                        TextInput::make('video_url')->label('Ruta o URL del Video (MP4)')->required()->placeholder('video/video_reconfortante_a.mp4'),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 3. EQUIPAMIENTO Y MODAL DE ESPACIOS
                                                Tab::make('3. Equipamiento')
                                                    ->icon('heroicon-o-home-modern')
                                                    ->schema([
                                                        Section::make('Sección Equipamiento / Departamentos')
                                                            ->description('Marcas aliadas, terminaciones, slides y galerías del modal Ver Detalles')
                                                            ->schema([
                                                                TextInput::make('equipment_eyebrow')->label('Eyebrow')->default('Equipamiento'),
                                                                TextInput::make('equipment_title')->label('Título')->placeholder('SOFISTICACIÓN EN CADA DETALLE'),
                                                                TextInput::make('equipment_bg_image_url')->label('Fondo Decorativo SVG/PNG')->placeholder('images/inn/Perspectiva.svg'),
                                                                RichEditor::make('equipment_description')->label('Descripción de Equipamiento')->columnSpanFull(),
                                                                Repeater::make('equipment_brands')
                                                                    ->label('Logos de Marcas Aliadas')
                                                                    ->schema([
                                                                        TextInput::make('src')->label('Ruta / URL del Logo')->required()->placeholder('images/logos/franke.png'),
                                                                        TextInput::make('alt')->label('Nombre de la Marca')->required()->placeholder('Franke'),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Repeater::make('equipment_slides')
                                                                    ->label('Slides de Equipamiento Principal')
                                                                    ->schema([
                                                                        TextInput::make('img')->label('Ruta / URL Imagen')->required()->placeholder('images/inn/equipamiento/Equipamiento_Principal_Cocina.jpg'),
                                                                        TextInput::make('alt')->label('Etiqueta')->required()->placeholder('Cocina'),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Repeater::make('equipment_spaces_modal')
                                                                    ->label('Galerías del Modal "Ver Detalles" (Por Ambiente)')
                                                                    ->schema([
                                                                        TextInput::make('label')->label('Nombre del Ambiente (ej: Cocina, Baños)')->required(),
                                                                        Repeater::make('images')
                                                                            ->label('Imágenes del Ambiente')
                                                                            ->schema([
                                                                                TextInput::make('img')->label('URL Imagen Grande')->required(),
                                                                                TextInput::make('alt')->label('Descripción / Título')->required(),
                                                                                TextInput::make('thumb')->label('Miniatura (Opcional)')->nullable(),
                                                                            ])
                                                                            ->columns(3)
                                                                            ->defaultItems(0),
                                                                    ])
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 4. RECORRIDOS 360 & MASTERPLAN
                                                Tab::make('4. Recorridos 360')
                                                    ->icon('heroicon-o-viewfinder-circle')
                                                    ->schema([
                                                        Section::make('Recorridos 360, Masterplan & Folleto')
                                                            ->description('Tours virtuales Matterport, 360 interactivos, folleto descargable y vistas')
                                                            ->schema([
                                                                TextInput::make('virtual_tour_matterport_url')->label('URL Matterport 3D')->placeholder('https://my.matterport.com/show/?m=...'),
                                                                TextInput::make('virtual_tour_360_url')->label('URL Recorrido 360 (La Nube 360)')->placeholder('https://www.lanube360.com/...'),
                                                                TextInput::make('virtual_tour_video_url')->label('URL Video Recorrido (YouTube / MP4)')->placeholder('https://...'),
                                                                TextInput::make('brochure_pdf_url')->label('URL Folleto / Brochure PDF')->placeholder('https://... o /storage/...'),
                                                                Repeater::make('virtual_tours_config')
                                                                    ->label('Tours y Subvistas Personalizadas (Masterplan)')
                                                                    ->schema([
                                                                        TextInput::make('label')->label('Pestaña (ej: Masterplan, Piloto 360)')->required(),
                                                                        TextInput::make('title')->label('Título')->required(),
                                                                        TextInput::make('src')->label('URL o Archivo Principal (Iframe / Imagen)')->nullable(),
                                                                        Repeater::make('images')
                                                                            ->label('Subvistas (si es Masterplan multipiso)')
                                                                            ->schema([
                                                                                TextInput::make('title')->label('Nombre de Subvista (ej: Piso 1)')->required(),
                                                                                TextInput::make('src')->label('Ruta / URL Imagen')->required(),
                                                                            ])
                                                                            ->columns(2)
                                                                            ->defaultItems(0),
                                                                    ])
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 5. ESPACIOS COMUNES (AMENIDADES)
                                                Tab::make('5. Espacios Comunes')
                                                    ->icon('heroicon-o-building-storefront')
                                                    ->schema([
                                                        Section::make('Espacios Comunes (Amenidades)')
                                                            ->description('Carrusel de espacios comunes con íconos animados y fotografías')
                                                            ->schema([
                                                                TextInput::make('espacios_eyebrow')->label('Eyebrow')->default('Espacios'),
                                                                TextInput::make('espacios_title')->label('Título')->placeholder('ESPACIOS DE OTRO NIVEL'),
                                                                TextInput::make('espacios_bg_image_url')->label('Fondo Decorativo SVG/PNG')->placeholder('images/inn/Climbing.svg'),
                                                                RichEditor::make('espacios_description')->label('Descripción de Espacios Comunes')->columnSpanFull(),
                                                                Repeater::make('espacios_items')
                                                                    ->label('Lista de Espacios Comunes / Amenidades')
                                                                    ->schema([
                                                                        TextInput::make('id')->label('ID Único (ej: hall, gourmet, piscina)')->required(),
                                                                        TextInput::make('label')->label('Nombre con salto (ej: Sala gourmet con<br />quincho)')->required(),
                                                                        Select::make('icon_key')
                                                                            ->label('Ícono')
                                                                            ->options([
                                                                                'bell' => 'Conserjería / Acceso (Bell)',
                                                                                'table' => 'Atrio / Mesa (Table)',
                                                                                'chef-hat' => 'Quincho / Gourmet (Chef Hat)',
                                                                                'dumbbell' => 'Training Zone / Gimnasio (Dumbbell)',
                                                                                'hot-tub' => 'Rooftop / Jacuzzi (Hot Tub)',
                                                                                'waves-ladder' => 'Piscina Climatizada (Pool)',
                                                                                'kayak' => 'Bodega Náutica (Kayak)',
                                                                            ])
                                                                            ->default('bell')
                                                                            ->required(),
                                                                        TextInput::make('img')->label('Ruta / URL Imagen')->required()->placeholder('images/inn/eecc/EECC_01.jpg'),
                                                                        TextInput::make('link')->label('URL Imagen Ampliable (Fancybox)')->nullable(),
                                                                        TextInput::make('alt')->label('Texto Alternativo')->required(),
                                                                    ])
                                                                    ->columns(3)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 6. MAPA INTERACTIVO Y POIS
                                                Tab::make('6. Ubicación & Mapa')
                                                    ->icon('heroicon-o-map-pin')
                                                    ->schema([
                                                        Section::make('Ubicación & Mapa Interactivo')
                                                            ->description('Mapa gráfico con pin del proyecto y puntos de interés sincronizados')
                                                            ->schema([
                                                                TextInput::make('map_eyebrow')->label('Eyebrow')->default('Ubicación'),
                                                                TextInput::make('map_title')->label('Título Mapa')->placeholder('Entorno INNigualable'),
                                                                TextInput::make('map_logo_url')->label('Logo Decorativo')->placeholder('images/inn/E_titulo.png'),
                                                                TextInput::make('map_btn_text')->label('Texto del Botón')->default('Experiencias'),
                                                                TextInput::make('map_address')->label('Dirección Visible')->placeholder('Vicente Pérez Rosales 991, Puerto Varas'),
                                                                CuratorPicker::make('entorno_map_image_id')->label('Imagen del Mapa (Curator)'),
                                                                TextInput::make('entorno_map_image_url')->label('URL Imagen del Mapa (Fallback)')->placeholder('images/inn/Mapa_Ok_Sitio.png'),
                                                                TextInput::make('map_pin_x')->label('Coordenada X del Proyecto (%)')->numeric()->default(33.3),
                                                                TextInput::make('map_pin_y')->label('Coordenada Y del Proyecto (%)')->numeric()->default(72.4),
                                                                TextInput::make('map_pin_logo_url')->label('Logo del Pin de Proyecto')->placeholder('images/inn/pin_logo.png'),
                                                                RichEditor::make('map_description')->label('Descripción de Ubicación & Entorno')->columnSpanFull(),
                                                                Repeater::make('map_pois')
                                                                    ->label('Puntos de Interés Interactivos (POIs)')
                                                                    ->schema([
                                                                        TextInput::make('name')->label('Nombre')->required(),
                                                                        TextInput::make('label')->label('Etiqueta en Mapa')->nullable(),
                                                                        TextInput::make('category')->label('Categoría')->placeholder('Gastronomía, Supermercado, etc.')->required(),
                                                                        TextInput::make('distance')->label('Distancia')->placeholder('1.2 km')->required(),
                                                                        TextInput::make('time')->label('Tiempo Caminando')->placeholder('12 minutos')->required(),
                                                                        TextInput::make('x')->label('Posición X (%)')->numeric()->required(),
                                                                        TextInput::make('y')->label('Posición Y (%)')->numeric()->required(),
                                                                        Select::make('direction')
                                                                            ->label('Dirección Pin')
                                                                            ->options([
                                                                                'right' => 'Derecha',
                                                                                'left' => 'Izquierda',
                                                                                'down' => 'Abajo',
                                                                                'top' => 'Arriba',
                                                                            ])
                                                                            ->default('right'),
                                                                        TextInput::make('img')->label('Ruta / URL Foto')->placeholder('images/inn/ubicacion/Mesa_Tropera_AI.jpg')->required(),
                                                                    ])
                                                                    ->columns(3)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 7. INTERIORISMO
                                                Tab::make('7. Interiorismo')
                                                    ->icon('heroicon-o-paint-brush')
                                                    ->schema([
                                                        Section::make('Interiorismo & Diseñadores')
                                                            ->description('Equipo de diseño de interiores, iluminación y propuestas de autor')
                                                            ->schema([
                                                                TextInput::make('interiorismo_eyebrow')->label('Eyebrow')->default('Interiorismo'),
                                                                TextInput::make('interiorismo_title')->label('Título')->placeholder('MAESTRÍA EN CADA DETALLE'),
                                                                TextInput::make('interiorismo_bg_image_url')->label('Fondo Decorativo SVG/PNG')->placeholder('images/inn/Interiorismo.svg'),
                                                                RichEditor::make('interiorismo_description')->label('Descripción de Interiorismo')->columnSpanFull(),
                                                                Repeater::make('designers')
                                                                    ->label('Lista de Diseñadores / Interioristas')
                                                                    ->schema([
                                                                        TextInput::make('name')->label('Nombre y Cargo (HTML: Sofía<br/><small>Interiorista</small>)')->required(),
                                                                        TextInput::make('image')->label('Ruta / URL Foto')->required()->placeholder('images/inn/Foto_Iturralde.jpg'),
                                                                        ColorPicker::make('color')->label('Color de Acento')->default('#d9bc70'),
                                                                        TextInput::make('alt')->label('Texto Alternativo')->nullable(),
                                                                        Textarea::make('text')->label('Biografía o Enfoque de Diseño')->rows(3)->nullable()->columnSpanFull(),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 8. MODAL PANORAMAS / EXPERIENCIAS
                                                Tab::make('8. Panoramas')
                                                    ->icon('heroicon-o-photo')
                                                    ->schema([
                                                        Section::make('Modal Panoramas / Experiencias del Sur')
                                                            ->description('Categorías y tarjetas de turismo, gastronomía y paseos en la zona')
                                                            ->schema([
                                                                Repeater::make('panoramas_categories')
                                                                    ->label('Categorías de Panoramas')
                                                                    ->schema([
                                                                        TextInput::make('id')->label('ID de Categoría (ej: ruta-del-lago)')->required(),
                                                                        TextInput::make('name')->label('Nombre de Categoría')->required(),
                                                                        TextInput::make('tabLabel')->label('Etiqueta Pestaña')->required(),
                                                                        Repeater::make('slides')
                                                                            ->label('Tarjetas de Panoramas')
                                                                            ->schema([
                                                                                TextInput::make('id')->label('ID')->required(),
                                                                                TextInput::make('title')->label('Título del Destino')->required(),
                                                                                TextInput::make('distance')->label('Distancia')->placeholder('40 km')->required(),
                                                                                TextInput::make('image')->label('Ruta / URL Foto')->placeholder('images/inn/panoramas/Ensenada_04_IA.jpg')->required(),
                                                                            ])
                                                                            ->columns(2)
                                                                            ->defaultItems(0),
                                                                    ])
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ]),
                                                    ]),

                                                // 9. SALA DE VENTAS & CONTACTO
                                                Tab::make('9. Contacto & Ventas')
                                                    ->icon('heroicon-o-phone')
                                                    ->schema([
                                                        Section::make('Sala de Ventas & Contacto')
                                                            ->description('Encabezados, horarios de atención, mapa Waze y contacto')
                                                            ->schema([
                                                                TextInput::make('contact_eyebrow')->label('Eyebrow')->default('Contacto'),
                                                                TextInput::make('contact_title')->label('Título')->default('Conversemos'),
                                                                TextInput::make('contact_waze_url')->label('URL Embed de Waze (Iframe)')->placeholder('https://embed.waze.com/...'),
                                                                RichEditor::make('contact_subtitle')->label('Texto de Dirección y Horarios')->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
