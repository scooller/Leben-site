<?php

namespace App\Filament\Resources\ShortLinks\Schemas;

use App\Enums\ShortLinkStatus;
use App\Models\ShortLink;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShortLinkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle')
                    ->columns(2)
                    ->components([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->copyable(),
                        TextEntry::make('short_url')
                            ->label('URL corta')
                            ->state(fn (ShortLink $record): string => $record->shortUrl())
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('destination_url')
                            ->label('URL destino')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(function (ShortLinkStatus|string|null $state): string {
                                $status = $state instanceof ShortLinkStatus
                                    ? $state
                                    : ShortLinkStatus::fromValue((string) $state);

                                return $status?->color() ?? 'gray';
                            })
                            ->icon(function (ShortLinkStatus|string|null $state): string {
                                $status = $state instanceof ShortLinkStatus
                                    ? $state
                                    : ShortLinkStatus::fromValue((string) $state);

                                return $status?->icon() ?? 'heroicon-o-question-mark-circle';
                            })
                            ->formatStateUsing(function (ShortLinkStatus|string|null $state): string {
                                $status = $state instanceof ShortLinkStatus
                                    ? $state
                                    : ShortLinkStatus::fromValue((string) $state);

                                return $status?->label() ?? '-';
                            }),
                        TextEntry::make('tag_manager_id')
                            ->label('GTM override')
                            ->placeholder('Usa configuracion global'),
                        TextEntry::make('visits_count')
                            ->label('Visitas totales')
                            ->numeric(),
                        TextEntry::make('last_visited_at')
                            ->label('Ultima visita')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('expires_at')
                            ->label('Expira')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('creator.name')
                            ->label('Creado por')
                            ->placeholder('Sistema'),
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Metadata')
                    ->description('Metadata guarda etiquetas internas del link (operacion/reportes). La atribucion publicitaria se registra por UTM en las visitas del short link.')
                    ->components([
                        KeyValueEntry::make('metadata')
                            ->label('Metadata')
                            ->placeholder('Sin metadata')
                            ->state(
                                fn (ShortLink $record): array => collect($record->metadata ?? [])
                                    ->map(fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value)
                                    ->toArray()
                            ),
                    ]),
            ]);
    }
}
