<?php

namespace App\Filament\Resources\Proyectos\Schemas;

use App\Models\Proyecto;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Curator\Models\Media;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProyectoForm
{
    public static function formatCuratorState(mixed $state): array
    {
        if (blank($state)) {
            return [];
        }

        if (is_array($state) && ! isset($state['id'])) {
            return $state;
        }

        $ids = Arr::wrap($state);
        $media = function_exists('get_media_items') ? get_media_items($ids)->toArray() : [];
        $items = [];
        foreach ($media as $itemData) {
            $items[(string) Str::uuid()] = $itemData;
        }

        return $items;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
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

                                        TextInput::make('cotiza_url')
                                            ->label('URL Cotizar por defecto')
                                            ->beforeLabel(Icon::make(Heroicon::GlobeAlt))
                                            ->url()
                                            ->placeholder('https://...')
                                            ->maxLength(2048)
                                            ->helperText('URL base del cotizador para este proyecto. Al hacer clic en Cotizar se abrirá en un modal inyectando los parámetros UTM.')
                                            ->columnSpanFull(),
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

                                                $projectNames = Proyecto::query()
                                                    ->whereIn('slug', array_keys($codes))
                                                    ->pluck('name', 'slug')
                                                    ->all();

                                                $options = [];
                                                foreach ($codes as $slug => $code) {
                                                    $options[$code] = $projectNames[$slug] ?? (string) $slug;
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

                                                                TextInput::make('theme_colors.gradient')
                                                                    ->label('Gradiente (--lb-project-gradient)')
                                                                    ->placeholder('linear-gradient(135deg, #d9bc70 0%, #b89345 100%)')
                                                                    ->default('linear-gradient(135deg, #d9bc70 0%, #b89345 100%)')
                                                                    ->helperText('Haz clic en el botón de la derecha para abrir el editor visual de gradiente (colores y ángulo).')
                                                                    ->suffixAction(
                                                                        Action::make('openGradientBuilder')
                                                                            ->label('Editar')
                                                                            ->icon('heroicon-m-swatch')
                                                                            ->tooltip('Abrir editor visual de gradiente')
                                                                            ->modalHeading('Configurador de Gradiente CSS')
                                                                            ->modalDescription('Ajusta los colores inicial, final y el ángulo de inclinación para tu gradiente.')
                                                                            ->modalSubmitActionLabel('Aplicar Gradiente')
                                                                            ->modalWidth('lg')
                                                                            ->fillForm(function (Get $get): array {
                                                                                $current = $get('theme_colors.gradient');
                                                                                $deg = 135;
                                                                                $from = '#d9bc70';
                                                                                $to = '#b89345';

                                                                                if ($current && preg_match('/linear-gradient\(\s*(-?\d+)deg\s*,\s*([^,]+?)\s*(?:0%)?\s*,\s*([^,]+?)\s*(?:100%)?\s*\)/i', $current, $matches)) {
                                                                                    $deg = (int) $matches[1];
                                                                                    $from = trim($matches[2]);
                                                                                    $to = trim($matches[3]);
                                                                                }

                                                                                return [
                                                                                    'deg' => $deg,
                                                                                    'from' => $from,
                                                                                    'to' => $to,
                                                                                ];
                                                                            })
                                                                            ->form([
                                                                                Placeholder::make('modal_gradient_preview')
                                                                                    ->label('Vista Previa en Vivo')
                                                                                    ->columnSpanFull()
                                                                                    ->content(function (Get $get): HtmlString {
                                                                                        $deg = $get('deg') ?? 135;
                                                                                        $from = $get('from') ?: '#d9bc70';
                                                                                        $to = $get('to') ?: '#b89345';
                                                                                        $css = "linear-gradient({$deg}deg, {$from} 0%, {$to} 100%)";

                                                                                        return new HtmlString('
                                                                                            <div style="height: 60px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: '.$css.';"></div>
                                                                                            <div style="font-family: monospace; font-size: 11px; margin-top: 6px; opacity: .75; text-align: center;">'.$css.'</div>
                                                                                        ');
                                                                                    }),

                                                                                ColorPicker::make('from')
                                                                                    ->label('Color Inicial (0%)')
                                                                                    ->default('#d9bc70')
                                                                                    ->live()
                                                                                    ->required(),

                                                                                ColorPicker::make('to')
                                                                                    ->label('Color Final (100%)')
                                                                                    ->default('#b89345')
                                                                                    ->live()
                                                                                    ->required(),

                                                                                TextInput::make('deg')
                                                                                    ->label('Ángulo (0° - 360°)')
                                                                                    ->numeric()
                                                                                    ->default(135)
                                                                                    ->suffix('deg')
                                                                                    ->minValue(0)
                                                                                    ->maxValue(360)
                                                                                    ->live()
                                                                                    ->required(),
                                                                            ])
                                                                            ->action(function (array $data, Set $set): void {
                                                                                $deg = $data['deg'] ?? 135;
                                                                                $from = $data['from'] ?? '#d9bc70';
                                                                                $to = $data['to'] ?? '#b89345';
                                                                                $set('theme_colors.gradient', "linear-gradient({$deg}deg, {$from} 0%, {$to} 100%)");
                                                                            })
                                                                    ),
                                                                TextInput::make('hero_eyebrow')->label('Eyebrow Hero')->placeholder('Puerto Varas'),
                                                                Textarea::make('hero_title')
                                                                    ->label('Título Hero (SplitTitle)')
                                                                    ->placeholder("VIVE EL LUJO\nEN PUERTO VARAS")
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea (SplitTitle multilínea en web)'),
                                                                TextInput::make('hero_subtitle')->label('Subtítulo Hero')->nullable(),
                                                                TextInput::make('hero_video_url')->label('URL Video Hero (MP4)')->placeholder('https://... o video/inn-new.mp4'),
                                                                CuratorPicker::make('hero_video_media_id')
                                                                    ->label('Seleccionar Video Hero desde Curator')
                                                                    ->buttonLabel('Elegir Video Hero')
                                                                    ->acceptedFileTypes(['video/*'])
                                                                    ->dehydrated(false)
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                                        if ($state) {
                                                                            $media = Media::find($state);
                                                                            if ($media) {
                                                                                $set('hero_video_url', $media->url);
                                                                            }
                                                                        }
                                                                    }),
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
                                                                Textarea::make('project_title')
                                                                    ->label('Título Sección')
                                                                    ->placeholder("LOS MEJORES DEPARTAMENTOS\nDE PUERTO VARAS")
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                TextInput::make('project_highlight_offer')->label('Oferta Destacada (HTML)')->placeholder('paga el pie en <b>60</b> cuotas'),
                                                                RichEditor::make('project_description')->label('Descripción Editorial (HTML)')->columnSpanFull(),
                                                                Repeater::make('project_slides')
                                                                    ->label('Slides del Carrusel de Proyecto')
                                                                    ->schema([
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Imagen desde Curator')
                                                                            ->buttonLabel('Elegir Imagen')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('img')
                                                                            ->label('Ruta o URL de Imagen (Fallback)')
                                                                            ->placeholder('images/inn/proyecto/Proyecto_01_Acceso.jpg')
                                                                            ->helperText('Opcional si seleccionas desde Curator. Puedes ingresar una URL directa.')
                                                                            ->nullable(),
                                                                        TextInput::make('alt')->label('Texto Alternativo')->required()->placeholder('Acceso')->columnSpanFull(),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Section::make('Banner de Video Intermedio (Posterior a Proyecto)')
                                                                    ->description('Banner con video de fondo y texto sobrepuesto que se sitúa tras la sección de Proyecto (antes de Equipamiento)')
                                                                    ->collapsible()
                                                                    ->schema([
                                                                        TextInput::make('proyecto_video.eyebrow')
                                                                            ->label('Eyebrow / Badge Superior')
                                                                            ->placeholder('ej: Experiencia INN')
                                                                            ->nullable(),
                                                                        TextInput::make('proyecto_video.title')
                                                                            ->label('Título')
                                                                            ->placeholder('ej: Vistas Insospechadas')
                                                                            ->nullable(),
                                                                        TextInput::make('proyecto_video.text')
                                                                            ->label('Texto / Frase Principal sobre Video')
                                                                            ->placeholder('ej: Un estilo de vida único frente al lago y los volcanes')
                                                                            ->nullable()
                                                                            ->columnSpanFull(),
                                                                        TextInput::make('proyecto_video.video_url')
                                                                            ->label('Ruta o URL del Video (MP4)')
                                                                            ->placeholder('video/video_reconfortante_a.mp4')
                                                                            ->helperText('Opcional si seleccionas un video desde Curator. Puedes ingresar una URL directa.')
                                                                            ->nullable(),
                                                                        CuratorPicker::make('proyecto_video.video_media_id')
                                                                            ->label('Seleccionar Video desde Curator')
                                                                            ->buttonLabel('Elegir Video')
                                                                            ->acceptedFileTypes(['video/*'])
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                    ])
                                                                    ->columns(2)
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
                                                                Textarea::make('equipment_title')
                                                                    ->label('Título')
                                                                    ->placeholder("SOFISTICACIÓN\nEN CADA DETALLE")
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                CuratorPicker::make('equipment_bg_image_id')->label('Fondo Decorativo SVG/PNG (Curator)'),
                                                                TextInput::make('equipment_bg_image_url')->label('URL Fondo Decorativo (Fallback)')->placeholder('images/inn/Perspectiva.svg'),
                                                                RichEditor::make('equipment_description')->label('Descripción de Equipamiento')->columnSpanFull(),
                                                                Repeater::make('equipment_brands')
                                                                    ->label('Logos de Marcas Aliadas')
                                                                    ->schema([
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Logo desde Curator')
                                                                            ->buttonLabel('Elegir Logo')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('src')
                                                                            ->label('Ruta / URL del Logo (Fallback)')
                                                                            ->placeholder('images/logos/franke.png')
                                                                            ->helperText('Opcional si seleccionas desde Curator.')
                                                                            ->nullable(),
                                                                        TextInput::make('alt')->label('Nombre de la Marca')->required()->placeholder('Franke')->columnSpanFull(),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                                Repeater::make('equipment_slides')
                                                                    ->label('Slides de Equipamiento Principal')
                                                                    ->schema([
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Imagen desde Curator')
                                                                            ->buttonLabel('Elegir Imagen')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('img')
                                                                            ->label('Ruta / URL Imagen (Fallback)')
                                                                            ->placeholder('images/inn/equipamiento/Equipamiento_Principal_Cocina.jpg')
                                                                            ->helperText('Opcional si seleccionas desde Curator.')
                                                                            ->nullable(),
                                                                        TextInput::make('alt')->label('Etiqueta')->required()->placeholder('Cocina')->columnSpanFull(),
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
                                                                                CuratorPicker::make('media_id')
                                                                                    ->label('Seleccionar Imagen desde Curator')
                                                                                    ->buttonLabel('Elegir Imagen')
                                                                                    ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                                TextInput::make('img')
                                                                                    ->label('URL Imagen Grande (Fallback)')
                                                                                    ->helperText('Opcional si seleccionas desde Curator.')
                                                                                    ->nullable(),
                                                                                TextInput::make('alt')->label('Descripción / Título')->required(),
                                                                                TextInput::make('thumb')->label('Miniatura (Opcional)')->nullable(),
                                                                            ])
                                                                            ->columns(2)
                                                                            ->defaultItems(0),
                                                                    ])
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),

                                                                Section::make('Banner de Video Intermedio (Posterior a Equipamiento)')
                                                                    ->description('Banner con video de fondo y texto sobrepuesto que se sitúa tras la sección de Equipamiento (antes de Recorridos 360)')
                                                                    ->collapsible()
                                                                    ->schema([
                                                                        TextInput::make('equipamiento_video.eyebrow')
                                                                            ->label('Eyebrow / Badge Superior')
                                                                            ->placeholder('ej: Calidad & Confort')
                                                                            ->nullable(),
                                                                        TextInput::make('equipamiento_video.title')
                                                                            ->label('Título')
                                                                            ->placeholder('ej: Descubre todo lo que INN tiene para ofrecer')
                                                                            ->nullable(),
                                                                        TextInput::make('equipamiento_video.text')
                                                                            ->label('Texto / Frase Principal sobre Video')
                                                                            ->placeholder('ej: Descubre todo lo que INN tiene para ofrecer')
                                                                            ->nullable()
                                                                            ->columnSpanFull(),
                                                                        TextInput::make('equipamiento_video.video_url')
                                                                            ->label('Ruta o URL del Video (MP4)')
                                                                            ->placeholder('video/exterior.mp4')
                                                                            ->helperText('Opcional si seleccionas un video desde Curator. Puedes ingresar una URL directa.')
                                                                            ->nullable(),
                                                                        CuratorPicker::make('equipamiento_video.video_media_id')
                                                                            ->label('Seleccionar Video desde Curator')
                                                                            ->buttonLabel('Elegir Video')
                                                                            ->acceptedFileTypes(['video/*'])
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 4. RECORRIDOS 360 & MASTERPLAN
                                                Tab::make('4. Recorridos 360')
                                                    ->icon('heroicon-o-viewfinder-circle')
                                                    ->schema([
                                                        Section::make('Recorridos 360, Masterplan & Folleto')
                                                            ->description('Configura múltiples secciones de recorridos (Masterplan, Pilotos 360, Vistas) y sus interiores o subvistas asociadas.')
                                                            ->schema([
                                                                TextInput::make('virtual_tours_eyebrow')
                                                                    ->label('Eyebrow Sección')
                                                                    ->placeholder('Recorridos Virtuales')
                                                                    ->default('Recorridos Virtuales'),

                                                                TextInput::make('virtual_tours_title')
                                                                    ->label('Título Sección (SplitTitle)')
                                                                    ->placeholder('Descubre el Proyecto'),

                                                                TextInput::make('brochure_pdf_url')
                                                                    ->label('URL Folleto / Brochure PDF')
                                                                    ->placeholder('https://... o /storage/...')
                                                                    ->helperText('Opcional si seleccionas un archivo desde Curator. Puedes ingresar una URL directa.')
                                                                    ->nullable()
                                                                    ->columnSpan(1),

                                                                CuratorPicker::make('brochure_media_id')
                                                                    ->label('Seleccionar Folleto / PDF desde Curator')
                                                                    ->buttonLabel('Elegir Archivo')
                                                                    ->formatStateUsing(fn ($state) => static::formatCuratorState($state))
                                                                    ->columnSpan(1),

                                                                Repeater::make('virtual_tours_config')
                                                                    ->label('Secciones / Tours 360 del Proyecto')
                                                                    ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                                                        ? ($state['label'].(filled($state['title'] ?? null) ? " — {$state['title']}" : ''))
                                                                        : 'Nueva Sección de Recorrido')
                                                                    ->collapsible()
                                                                    ->schema([
                                                                        TextInput::make('label')
                                                                            ->label('Nombre de la Pestaña / Botón')
                                                                            ->placeholder('ej: Masterplan, Piloto 360, Vista 360')
                                                                            ->required(),

                                                                        TextInput::make('title')
                                                                            ->label('Título Descriptivo')
                                                                            ->placeholder('ej: Conoce tu próximo departamento')
                                                                            ->required(),

                                                                        TextInput::make('src')
                                                                            ->label('Ruta de Imagen o URL 360 / Iframe')
                                                                            ->placeholder('https://my.matterport.com/... o images/... o /storage/...')
                                                                            ->helperText('Opcional si seleccionas un archivo desde Curator. Ingresa una URL para iframes (Matterport/La Nube 360).')
                                                                            ->nullable(),

                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Imagen / Archivo desde Curator')
                                                                            ->buttonLabel('Elegir Archivo')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),

                                                                        Repeater::make('interiores')
                                                                            ->label('Interiores / Subvistas de esta Sección')
                                                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                                                            ->collapsible()
                                                                            ->schema([
                                                                                TextInput::make('title')
                                                                                    ->label('Nombre del Interior / Subvista')
                                                                                    ->placeholder('ej: Living Comedor 360, Piso 1, Terraza')
                                                                                    ->required()
                                                                                    ->columnSpanFull(),

                                                                                TextInput::make('src')
                                                                                    ->label('Ruta de Imagen o URL 360 (Iframe / Matterport)')
                                                                                    ->placeholder('images/... o https://my.matterport.com/... o /storage/...')
                                                                                    ->helperText('Opcional si seleccionas desde Curator. Ingresa una URL para iframes externos.')
                                                                                    ->nullable(),

                                                                                CuratorPicker::make('media_id')
                                                                                    ->label('Seleccionar Imagen / Archivo desde Curator')
                                                                                    ->buttonLabel('Elegir Archivo')
                                                                                    ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                            ])
                                                                            ->columns(2)
                                                                            ->defaultItems(0)
                                                                            ->reorderable()
                                                                            ->columnSpanFull()
                                                                            ->helperText('Si esta sección tiene múltiples ambientes o niveles, agrégalos aquí. Se mostrarán como botones de sub-navegación dentro de esta sección.'),
                                                                    ])
                                                                    ->columns(2)
                                                                    ->defaultItems(0)
                                                                    ->reorderable()
                                                                    ->columnSpanFull(),

                                                                Section::make('URLs Directas de Respaldo (Opcionales)')
                                                                    ->description('URLs directas heredadas si no se configuran secciones personalizadas arriba')
                                                                    ->collapsed()
                                                                    ->schema([
                                                                        TextInput::make('virtual_tour_matterport_url')->label('URL Matterport 3D')->placeholder('https://my.matterport.com/show/?m=...'),
                                                                        TextInput::make('virtual_tour_360_url')->label('URL Recorrido 360 (La Nube 360)')->placeholder('https://www.lanube360.com/...'),
                                                                        CuratorPicker::make('virtual_tour_360_media_id')
                                                                            ->label('Seleccionar Archivo 360 desde Curator')
                                                                            ->buttonLabel('Elegir Archivo 360')
                                                                            ->dehydrated(false)
                                                                            ->live()
                                                                            ->afterStateUpdated(function ($state, Set $set) {
                                                                                if ($state) {
                                                                                    $media = Media::find($state);
                                                                                    if ($media) {
                                                                                        $set('virtual_tour_360_url', $media->url);
                                                                                    }
                                                                                }
                                                                            }),
                                                                        TextInput::make('virtual_tour_video_url')->label('URL Video Recorrido (YouTube / MP4)')->placeholder('https://...'),
                                                                        CuratorPicker::make('virtual_tour_video_media_id')
                                                                            ->label('Seleccionar Video desde Curator')
                                                                            ->buttonLabel('Elegir Video')
                                                                            ->acceptedFileTypes(['video/*'])
                                                                            ->dehydrated(false)
                                                                            ->live()
                                                                            ->afterStateUpdated(function ($state, Set $set) {
                                                                                if ($state) {
                                                                                    $media = Media::find($state);
                                                                                    if ($media) {
                                                                                        $set('virtual_tour_video_url', $media->url);
                                                                                    }
                                                                                }
                                                                            }),
                                                                    ])
                                                                    ->columns(2)
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
                                                                Textarea::make('espacios_title')
                                                                    ->label('Título')
                                                                    ->placeholder("ESPACIOS DE\nOTRO NIVEL")
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                CuratorPicker::make('espacios_bg_image_id')->label('Fondo Decorativo SVG/PNG (Curator)'),
                                                                TextInput::make('espacios_bg_image_url')->label('URL Fondo Decorativo (Fallback)')->placeholder('images/inn/Climbing.svg'),
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
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Foto desde Curator')
                                                                            ->buttonLabel('Elegir Foto')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('img')
                                                                            ->label('Ruta / URL Imagen (Fallback)')
                                                                            ->placeholder('images/inn/eecc/EECC_01.jpg')
                                                                            ->helperText('Opcional si seleccionas desde Curator.')
                                                                            ->nullable(),
                                                                        TextInput::make('link')->label('URL Imagen Ampliable (Fancybox)')->nullable(),
                                                                        TextInput::make('alt')->label('Texto Alternativo')->required(),
                                                                    ])
                                                                    ->columns(3)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),

                                                                Section::make('Banner de Video Intermedio (Posterior a Espacios Comunes)')
                                                                    ->description('Banner con video de fondo y texto sobrepuesto que se sitúa tras la sección de Espacios Comunes (antes del Mapa)')
                                                                    ->collapsible()
                                                                    ->schema([
                                                                        TextInput::make('espacios_video.eyebrow')
                                                                            ->label('Eyebrow / Badge Superior')
                                                                            ->placeholder('ej: Naturaleza & Confort')
                                                                            ->nullable(),
                                                                        TextInput::make('espacios_video.title')
                                                                            ->label('Título')
                                                                            ->placeholder('ej: Región de Los Lagos')
                                                                            ->nullable(),
                                                                        TextInput::make('espacios_video.text')
                                                                            ->label('Texto / Frase Principal sobre Video')
                                                                            ->placeholder('ej: Despierta tu espíritu aventurero en la Región de Los Lagos')
                                                                            ->nullable()
                                                                            ->columnSpanFull(),
                                                                        TextInput::make('espacios_video.video_url')
                                                                            ->label('Ruta o URL del Video (MP4)')
                                                                            ->placeholder('video/lipsum.mp4')
                                                                            ->helperText('Opcional si seleccionas un video desde Curator. Puedes ingresar una URL directa.')
                                                                            ->nullable(),
                                                                        CuratorPicker::make('espacios_video.video_media_id')
                                                                            ->label('Seleccionar Video desde Curator')
                                                                            ->buttonLabel('Elegir Video')
                                                                            ->acceptedFileTypes(['video/*'])
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                    ])
                                                                    ->columns(2)
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
                                                                Textarea::make('map_title')
                                                                    ->label('Título Mapa')
                                                                    ->placeholder("Entorno\nINNigualable")
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                CuratorPicker::make('map_logo_id')->label('Logo Decorativo (Curator)'),
                                                                TextInput::make('map_logo_url')->label('URL Logo Decorativo (Fallback)')->placeholder('images/inn/E_titulo.png'),
                                                                TextInput::make('map_btn_text')->label('Texto del Botón')->default('Experiencias'),
                                                                TextInput::make('map_address')->label('Dirección Visible')->placeholder('Vicente Pérez Rosales 991, Puerto Varas'),
                                                                CuratorPicker::make('entorno_map_image_id')->label('Imagen del Mapa (Curator)'),
                                                                TextInput::make('map_pin_x')->label('Coordenada X del Proyecto (%)')->numeric()->default(33.3),
                                                                TextInput::make('map_pin_y')->label('Coordenada Y del Proyecto (%)')->numeric()->default(72.4),
                                                                CuratorPicker::make('map_pin_logo_id')->label('Logo del Pin de Proyecto (Curator)'),
                                                                TextInput::make('map_pin_logo_url')->label('URL Logo del Pin de Proyecto (Fallback)')->placeholder('images/inn/pin_logo.png'),
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
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Foto desde Curator')
                                                                            ->buttonLabel('Elegir Foto')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('img')
                                                                            ->label('Ruta / URL Foto (Fallback)')
                                                                            ->placeholder('images/inn/ubicacion/Mesa_Tropera_AI.jpg')
                                                                            ->helperText('Opcional si seleccionas desde Curator.')
                                                                            ->nullable(),
                                                                    ])
                                                                    ->columns(3)
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 7. MODAL PANORAMAS / EXPERIENCIAS (Ligado al botón Experiencias del Mapa)
                                                Tab::make('7. Panoramas')
                                                    ->icon('heroicon-o-photo')
                                                    ->schema([
                                                        Section::make('Modal Panoramas / Experiencias del Sur')
                                                            ->description('Categorías y tarjetas de turismo, gastronomía y paseos en la zona (activadas desde el botón del Mapa)')
                                                            ->schema([
                                                                CuratorPicker::make('panoramas_bg_image_id')
                                                                    ->label('Imagen de Fondo del Modal (Curator)'),

                                                                TextInput::make('panoramas_bg_image_url')
                                                                    ->label('URL directa de Imagen de Fondo (Fallback)')
                                                                    ->placeholder('images/inn/panoramas/bg-forest.jpg'),

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
                                                                                CuratorPicker::make('media_id')
                                                                                    ->label('Seleccionar Foto desde Curator')
                                                                                    ->buttonLabel('Elegir Foto')
                                                                                    ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                                TextInput::make('image')
                                                                                    ->label('Ruta / URL Foto (Fallback)')
                                                                                    ->placeholder('images/inn/panoramas/Ensenada_04_IA.jpg')
                                                                                    ->helperText('Opcional si seleccionas desde Curator.')
                                                                                    ->nullable(),
                                                                            ])
                                                                            ->columns(2)
                                                                            ->defaultItems(0),
                                                                    ])
                                                                    ->defaultItems(0)
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),

                                                // 8. INTERIORISMO
                                                Tab::make('8. Interiorismo')
                                                    ->icon('heroicon-o-paint-brush')
                                                    ->schema([
                                                        Section::make('Interiorismo & Diseñadores')
                                                            ->description('Equipo de diseño de interiores, iluminación y propuestas de autor')
                                                            ->schema([
                                                                TextInput::make('interiorismo_eyebrow')->label('Eyebrow')->default('Interiorismo'),
                                                                Textarea::make('interiorismo_title')
                                                                    ->label('Título')
                                                                    ->placeholder('MAESTRÍA EN CADA DETALLE')
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                CuratorPicker::make('interiorismo_bg_image_id')->label('Fondo Decorativo SVG/PNG (Curator)'),
                                                                TextInput::make('interiorismo_bg_image_url')->label('URL Fondo Decorativo (Fallback)')->placeholder('images/inn/Interiorismo.svg'),
                                                                RichEditor::make('interiorismo_description')->label('Descripción de Interiorismo')->columnSpanFull(),
                                                                Repeater::make('designers')
                                                                    ->label('Lista de Diseñadores / Interioristas')
                                                                    ->schema([
                                                                        TextInput::make('name')->label('Nombre y Cargo (HTML: Sofía<br/><small>Interiorista</small>)')->required(),
                                                                        CuratorPicker::make('media_id')
                                                                            ->label('Seleccionar Foto desde Curator')
                                                                            ->buttonLabel('Elegir Foto')
                                                                            ->formatStateUsing(fn ($state) => static::formatCuratorState($state)),
                                                                        TextInput::make('image')
                                                                            ->label('Ruta / URL Foto (Fallback)')
                                                                            ->placeholder('images/inn/Foto_Iturralde.jpg')
                                                                            ->helperText('Opcional si seleccionas desde Curator.')
                                                                            ->nullable(),
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

                                                // 9. SALA DE VENTAS & CONTACTO
                                                Tab::make('9. Contacto & Ventas')
                                                    ->icon('heroicon-o-phone')
                                                    ->schema([
                                                        Section::make('Sala de Ventas & Contacto')
                                                            ->description('Encabezados, horarios de atención, mapa Waze y contacto')
                                                            ->schema([
                                                                TextInput::make('contact_eyebrow')->label('Eyebrow')->default('Contacto'),
                                                                Textarea::make('contact_title')
                                                                    ->label('Título')
                                                                    ->default('Conversemos')
                                                                    ->rows(2)
                                                                    ->helperText('Presiona Enter para saltos de línea'),
                                                                TextInput::make('contact_waze_url')->label('URL Embed de Waze (Iframe)')->placeholder('https://embed.waze.com/...'),
                                                                RichEditor::make('contact_subtitle')->label('Texto de Dirección y Horarios')->columnSpanFull(),
                                                            ])
                                                            ->columns(2),
                                                    ]),
                                            ]),
                                    ]),
                            ]),

                        Tab::make('Galería de Imágenes')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Galería de Imágenes del Proyecto (Cotizador)')
                                    ->description('Selecciona o sube múltiples imágenes para el cotizador y la galería interactiva en el frontend React. Puedes reordenarlas arrastrándolas.')
                                    ->schema([
                                        CuratorPicker::make('gallery_image_ids')
                                            ->label('Imágenes de la Galería')
                                            ->buttonLabel('Seleccionar Imágenes')
                                            ->multiple()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
