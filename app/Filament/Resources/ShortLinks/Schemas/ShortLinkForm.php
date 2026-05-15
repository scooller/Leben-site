<?php

namespace App\Filament\Resources\ShortLinks\Schemas;

use App\Enums\ShortLinkStatus;
use App\Models\ShortLink;
use App\Models\SiteSetting;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShortLinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Link corto')
                    ->description('UTM (utm_source, utm_medium, utm_campaign, etc.) se pasan por query string en la URL destino y se registran por visita. Ejemplo base: https://api.whatsapp.com/send/?phone=56942221542&text=Hola&type=phone_number&app_absent=0. Ejemplo con UTM (formato correcto): https://api.whatsapp.com/send/?phone=56942221542&text=Hola&type=phone_number&app_absent=0&utm_source=Brevo&utm_medium=Black_Inmobiliario_Icon_SUR&utm_campaign=BlackInmobiliario&utm_content=mail_black_inmobiliario_icon_sur_indigo_280426&utm_term=Clic_boton. Metadata es para clasificacion interna del link y no reemplaza UTM ni eventos de Google Analytics, Meta Pixel o TikTok Pixel.')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label('Titulo')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->minLength(2)
                            ->maxLength(32)
                            ->alphaDash()
                            ->scopedUnique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'No disponible: este slug ya existe.',
                            ])
                            ->extraInputAttributes([
                                'x-on:blur' => "console.log('[ShortLink.slug] blur', { value: \$event.target.value, name: \$event.target.name, id: \$event.target.id });",
                                'x-on:focusout' => "console.log('[ShortLink.slug] focusout', { value: \$event.target.value, name: \$event.target.name, id: \$event.target.id });",
                            ])
                            ->live(onBlur: true)
                            ->partiallyRenderComponentsAfterStateUpdated(['slugAvailability'])
                            ->default(fn(): string => Str::lower(Str::random(2)))
                            ->helperText('Se usa en la URL corta /s/{slug}.'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(ShortLinkStatus::toSelectArray())
                            ->searchable()
                            ->required()
                            ->default(ShortLinkStatus::ACTIVE->value),
                        Text::make(function (Get $get, ?Model $record): ?string {
                            $slug = $get('slug');

                            if (blank($slug)) {
                                return null;
                            }

                            $slugExists = static::slugExists($slug, $record);

                            return $slugExists ? 'No disponible' : 'Disponible';
                        })
                            ->key('slugAvailability')
                            ->hidden(fn(Get $get): bool => blank($get('slug')))
                            ->color(function (Get $get, ?Model $record): string {
                                $slug = $get('slug');

                                if (blank($slug)) {
                                    return 'gray';
                                }

                                $slugExists = static::slugExists($slug, $record);

                                return $slugExists ? 'danger' : 'success';
                            })
                            ->weight('medium'),
                        TextInput::make('destination_url')
                            ->label('URL destino')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->helperText('Si necesitas atribucion de campana, agrega UTM en la misma URL destino. Si la URL ya trae parametros (por ejemplo, en WhatsApp), continua con &utm_... y no uses un segundo ?.')
                            ->columnSpanFull(),
                        TextInput::make('tag_manager_id')
                            ->label('GTM ID (override)')
                            ->placeholder('GTM-XXXXXXX')
                            ->maxLength(50)
                            ->regex('/^GTM-[A-Z0-9]+$/')
                            ->default(fn(): ?string => SiteSetting::get('tag_manager_id') ?: null)
                            ->helperText('Si se deja vacio, usa el tag_manager_id global de Site Settings.'),
                        DateTimePicker::make('expires_at')
                            ->label('Expira en')
                            ->seconds(false),
                        KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Clave')
                            ->valueLabel('Valor')
                            ->helperText('Uso recomendado: contexto interno para reportes y segmentacion (ej: canal=paid_social, plataforma=tiktok_ads, creativo=video_a, objetivo=lead_gen).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function slugExists(string $slug, ?Model $record): bool
    {
        $currentRecordKey = $record?->exists ? $record->getKey() : null;

        $slugExists = ShortLink::query()
            ->where('slug', $slug)
            ->when(
                filled($currentRecordKey),
                fn($query) => $query->whereKeyNot($currentRecordKey),
            )
            ->exists();

        Log::info('short-link slug availability check', [
            'slug' => $slug,
            'current_record_key' => $currentRecordKey,
            'slug_exists' => $slugExists,
        ]);

        return $slugExists;
    }
}
