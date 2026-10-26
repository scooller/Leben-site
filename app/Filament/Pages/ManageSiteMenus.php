<?php

namespace App\Filament\Pages;

use App\Models\Menu;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;
use UnitEnum;

class ManageSiteMenus extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Menús';

    protected static ?string $title = 'Gestor de Menús (Cabecera y Footer)';

    protected static string|UnitEnum|null $navigationGroup = 'Contenido Web';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.manage-site-menus';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return (Auth::user()?->isAdmin() ?? false) || (Auth::user()?->isMarketing() ?? false);
    }

    public static function canAccess(): bool
    {
        return (Auth::user()?->isAdmin() ?? false) || (Auth::user()?->isMarketing() ?? false);
    }

    public function mount(): void
    {
        $headerMain = Menu::firstOrCreate(['location' => 'header_main'], ['name' => 'Cabecera Principal']);
        $headerDropdown = Menu::firstOrCreate(['location' => 'header_dropdown'], ['name' => 'Dropdown Proyectos']);
        $footerCorporate = Menu::firstOrCreate(['location' => 'footer_corporate'], ['name' => 'Footer Corporativo']);
        $footerLegal = Menu::firstOrCreate(['location' => 'footer_legal'], ['name' => 'Footer Legal']);

        $this->form->fill([
            'header_main' => $headerMain->allItems()->get()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target ?? '_self',
                'badge' => $item->badge,
                'is_featured' => (bool) $item->is_featured,
                'is_active' => (bool) $item->is_active,
            ])->toArray(),
            'header_dropdown' => $headerDropdown->allItems()->get()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target ?? '_self',
                'badge' => $item->badge,
                'is_featured' => (bool) $item->is_featured,
                'is_active' => (bool) $item->is_active,
            ])->toArray(),
            'footer_corporate' => $footerCorporate->allItems()->get()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target ?? '_self',
                'badge' => $item->badge,
                'is_featured' => (bool) $item->is_featured,
                'is_active' => (bool) $item->is_active,
            ])->toArray(),
            'footer_legal' => $footerLegal->allItems()->get()->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target ?? '_self',
                'badge' => $item->badge,
                'is_featured' => (bool) $item->is_featured,
                'is_active' => (bool) $item->is_active,
            ])->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Tabs::make('Menus')
                    ->tabs([
                        Tabs\Tab::make('Menú General (Cabecera)')
                            ->icon('heroicon-o-bars-3')
                            ->schema([
                                Section::make('Enlaces Principales de Navegación (Navbar)')
                                    ->description('Enlaces de la barra superior. Arrastra para reordenar, agrega nuevos o elimina.')
                                    ->schema([
                                        Repeater::make('header_main')
                                            ->label('Enlaces de Cabecera')
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar Enlace al Menú General')
                                            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                                ? ($state['label'].' ('.($state['url'] ?? '').')')
                                                : 'Nuevo Enlace'
                                            )
                                            ->schema(self::getItemSchema())
                                            ->columns(3),
                                    ]),

                                Section::make('Dropdown de Proyectos Destacados (Opcional)')
                                    ->description('Sub-enlaces o accesos rápidos de proyectos en el menú desplegable.')
                                    ->schema([
                                        Repeater::make('header_dropdown')
                                            ->label('Enlaces del Dropdown')
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar Enlace al Dropdown')
                                            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                                ? ($state['label'].' ('.($state['url'] ?? '').')')
                                                : 'Nuevo Enlace'
                                            )
                                            ->schema(self::getItemSchema())
                                            ->columns(3),
                                    ])
                                    ->collapsed(),
                            ]),

                        Tabs\Tab::make('Menú Footer (Pie de Página)')
                            ->icon('heroicon-o-rectangle-group')
                            ->schema([
                                Section::make('Enlaces Corporativos')
                                    ->description('Enlaces del pie de página para Proyectos, Empresa, Sala de Ventas, etc.')
                                    ->schema([
                                        Repeater::make('footer_corporate')
                                            ->label('Enlaces Corporativos')
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar Enlace Corporativo')
                                            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                                ? ($state['label'].' ('.($state['url'] ?? '').')')
                                                : 'Nuevo Enlace'
                                            )
                                            ->schema(self::getItemSchema())
                                            ->columns(3),
                                    ]),

                                Section::make('Enlaces Legales')
                                    ->description('Enlaces para Bases Legales, Términos y Condiciones, Políticas de Privacidad y Cookies.')
                                    ->schema([
                                        Repeater::make('footer_legal')
                                            ->label('Enlaces Legales')
                                            ->reorderable()
                                            ->collapsible()
                                            ->cloneable()
                                            ->defaultItems(0)
                                            ->addActionLabel('Agregar Enlace Legal')
                                            ->itemLabel(fn (array $state): ?string => filled($state['label'] ?? null)
                                                ? ($state['label'].' ('.($state['url'] ?? '').')')
                                                : 'Nuevo Enlace'
                                            )
                                            ->schema(self::getItemSchema())
                                            ->columns(3),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function getItemSchema(): array
    {
        return [
            TextInput::make('label')
                ->label('Texto / Etiqueta')
                ->placeholder('Ej: Proyectos')
                ->required()
                ->maxLength(150),

            TextInput::make('url')
                ->label('URL / Ruta')
                ->placeholder('Ej: /proyectos o https://...')
                ->required()
                ->maxLength(500),

            Select::make('target')
                ->label('Destino')
                ->options([
                    '_self' => 'Misma ventana (_self)',
                    '_blank' => 'Nueva pestaña (_blank)',
                ])
                ->default('_self')
                ->required(),

            TextInput::make('badge')
                ->label('Badge / Etiqueta')
                ->placeholder('Ej: Nuevo, Hot')
                ->maxLength(50)
                ->nullable(),

            Toggle::make('is_featured')
                ->label('Botón Destacado')
                ->default(false),

            Toggle::make('is_active')
                ->label('Activo')
                ->default(true),
        ];
    }

    public function save(): void
    {
        $formData = $this->form->getState();

        $locations = [
            'header_main' => 'Cabecera Principal',
            'header_dropdown' => 'Dropdown Proyectos',
            'footer_corporate' => 'Footer Corporativo',
            'footer_legal' => 'Footer Legal',
        ];

        foreach ($locations as $location => $name) {
            $menu = Menu::firstOrCreate(['location' => $location], ['name' => $name]);
            $items = $formData[$location] ?? [];

            $menu->allItems()->delete();

            foreach ($items as $index => $itemData) {
                if (blank($itemData['label'] ?? null) || blank($itemData['url'] ?? null)) {
                    continue;
                }

                $menu->allItems()->create([
                    'label' => trim((string) $itemData['label']),
                    'url' => trim((string) $itemData['url']),
                    'target' => $itemData['target'] ?? '_self',
                    'badge' => filled($itemData['badge'] ?? null) ? trim((string) $itemData['badge']) : null,
                    'is_featured' => (bool) ($itemData['is_featured'] ?? false),
                    'is_active' => (bool) ($itemData['is_active'] ?? true),
                    'sort_order' => $index,
                ]);
            }

            Cache::forget("menu_location_{$location}");
        }

        try {
            Cache::tags(['menus'])->flush();
        } catch (Throwable) {
        }

        Notification::make()
            ->title('Menús guardados exitosamente')
            ->success()
            ->send();
    }
}
