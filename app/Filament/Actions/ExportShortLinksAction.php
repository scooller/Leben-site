<?php

namespace App\Filament\Actions;

use App\Models\ShortLink;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Response;

class ExportShortLinksAction extends Action
{
    public static function make(?string $name = 'exportShortLinks'): static
    {
        return parent::make($name)
            ->label('Exportar CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                $fileName = 'short-links-'.now()->format('Y-m-d-His').'.csv';
                $headers = [
                    'Slug',
                    'URL Corta',
                    'Destino',
                    'Estado',
                    'Visitas',
                    'Ultima Visita',
                    'Expira',
                    'Creado por',
                    'Creado',
                ];

                $rows = ShortLink::query()
                    ->with('creator')
                    ->get()
                    ->map(function (ShortLink $link) {
                        return [
                            $link->slug,
                            $link->shortUrl(),
                            $link->destination_url,
                            $link->status?->label() ?? '-',
                            $link->visits_count,
                            $link->last_visited_at?->format('d/m/Y H:i') ?? '-',
                            $link->expires_at?->format('d/m/Y H:i') ?? '-',
                            $link->creator?->name ?? 'Sistema',
                            $link->created_at->format('d/m/Y H:i'),
                        ];
                    })
                    ->toArray();

                return Response::streamDownload(
                    function () use ($headers, $rows) {
                        $file = fopen('php://output', 'w');
                        fputcsv($file, $headers, ',');

                        foreach ($rows as $row) {
                            fputcsv($file, $row, ',');
                        }

                        fclose($file);
                    },
                    $fileName,
                    ['Content-Type' => 'text/csv; charset=UTF-8']
                );
            });
    }
}
