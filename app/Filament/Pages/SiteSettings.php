<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración del Sitio';

    protected static ?string $title = 'Configuración del Sitio';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.site-settings';

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
                                            ->label('URL del Sitio')
                                            ->url()
                                            ->placeholder('https://ejemplo.com'),
                                    ])
                                    ->columns(1),
                            ]),

                        Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Section::make('Logos e Íconos')
                                    ->description('Sube las imágenes de tu marca')
                                    ->schema([
                                        FileUpload::make('logo')
                                            ->label('Logo Principal')
                                            ->disk('branding')
                                            ->directory('.')
                                            ->image()
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->helperText('Recomendado: PNG con fondo transparente'),

                                        FileUpload::make('logo_dark')
                                            ->label('Logo Modo Oscuro')
                                            ->disk('branding')
                                            ->directory('.')
                                            ->image()
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->helperText('Versión del logo para fondos oscuros'),

                                        FileUpload::make('icon')
                                            ->label('Ícono/Isotipo')
                                            ->disk('branding')
                                            ->directory('.')
                                            ->image()
                                            ->visibility('public')
                                            ->imageEditor()
                                            ->helperText('Ícono cuadrado, mínimo 512x512px'),

                                        FileUpload::make('favicon')
                                            ->label('Favicon')
                                            ->disk('branding')
                                            ->directory('.')
                                            ->image()
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/x-icon', 'image/png'])
                                            ->helperText('ICO o PNG, 32x32px o 64x64px'),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Colores')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                Section::make('Tema de Colores')
                                    ->description('Define la paleta de colores del sitio')
                                    ->schema([
                                        ColorPicker::make('primary_color')
                                            ->label('Color Primario')
                                            ->required(),

                                        ColorPicker::make('secondary_color')
                                            ->label('Color Secundario')
                                            ->required(),

                                        ColorPicker::make('accent_color')
                                            ->label('Color de Acento'),

                                        ColorPicker::make('background_color')
                                            ->label('Color de Fondo')
                                            ->required(),

                                        ColorPicker::make('text_color')
                                            ->label('Color de Texto')
                                            ->required(),
                                    ])
                                    ->columns(2),
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

                                Section::make('JavaScript Personalizado')
                                    ->schema([
                                        Textarea::make('custom_js')
                                            ->label('JavaScript Adicional')
                                            ->rows(10)
                                            ->helperText('JavaScript que se ejecutará en el frontend'),
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

                                        Textarea::make('maintenance_message')
                                            ->label('Mensaje de Mantenimiento')
                                            ->rows(3)
                                            ->default('Estamos realizando mejoras. Volveremos pronto.')
                                            ->visible(fn ($get) => $get('maintenance_mode')),
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
