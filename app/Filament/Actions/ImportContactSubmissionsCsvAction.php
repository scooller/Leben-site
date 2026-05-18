<?php

namespace App\Filament\Actions;

use App\Jobs\CreateSalesforceCaseJob;
use App\Models\ContactChannel;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use App\Services\ContactImport\ContactCsvParser;
use App\Services\ContactImport\ContactCsvRowMapper;
use App\Services\ContactImport\ContactTextHomologationService;
use Awcodes\Curator\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportContactSubmissionsCsvAction
{
    public static function make(): Action
    {
        return Action::make('importContactCsv')
            ->label('Importar CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->modalHeading('Importar contactos desde CSV')
            ->modalSubmitActionLabel('Importar')
            ->modalWidth('7xl')
            ->steps([
                Step::make('Archivo')
                    ->description('Sube el archivo CSV y revisa una vista previa.')
                    ->schema([
                        Select::make('csv_source')
                            ->label('Origen del CSV')
                            ->options([
                                'upload' => 'Subir archivo',
                                'files' => 'Elegir desde Archivos',
                            ])
                            ->default('upload')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->required(),
                        FileUpload::make('csv_file')
                            ->label('Archivo CSV')
                            ->disk('local')
                            ->directory('imports/contact-submissions')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->visible(fn (Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'upload')
                            ->required(fn (Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'upload')
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->live(),
                        Select::make('curator_media_id')
                            ->label('Archivo CSV desde Archivos')
                            ->options(fn (): array => self::csvMediaOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'files')
                            ->required(fn (Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'files')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            }),
                        Select::make('delimiter')
                            ->label('Delimitador')
                            ->options([
                                ',' => 'Coma (,)',
                                ';' => 'Punto y coma (;)',
                                "\t" => 'Tabulador',
                                '|' => 'Pipe (|)',
                            ])
                            ->default(',')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->required(),
                        Toggle::make('has_header')
                            ->label('El CSV contiene encabezados en la primera fila')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->default(true),
                        Actions::make([
                            Action::make('refresh_preview')
                                ->label('Actualizar vista previa')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(function (Get $get, Set $set): void {
                                    self::prefillSuggestedMappings($get, $set);
                                    $set('preview_refresh_key', now()->format('YmdHisv'));
                                }),
                        ])
                            ->columnSpanFull(),
                        TextInput::make('preview_refresh_key')
                            ->hidden()
                            ->dehydrated(false)
                            ->default(''),
                        Textarea::make('preview')
                            ->label('Vista previa')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function (Get $get): string {
                                // Force reactive recalculation when user clicks "Actualizar vista previa".
                                $get('preview_refresh_key');

                                $parsed = self::parseCsvState(
                                    csvSource: (string) ($get('csv_source') ?? 'upload'),
                                    csvState: $get('csv_file'),
                                    curatorMediaId: $get('curator_media_id'),
                                    delimiter: self::normalizeDelimiter((string) $get('delimiter')),
                                    hasHeader: (bool) ($get('has_header') ?? true),
                                );

                                if (($parsed['error'] ?? null) === 'missing_file') {
                                    return 'Sube un CSV para ver la vista previa.';
                                }

                                if (filled($parsed['error'] ?? null)) {
                                    return 'Error al leer CSV: '.$parsed['error'];
                                }

                                $lines = [];
                                $lines[] = 'Encabezados: '.implode(' | ', $parsed['headers']);
                                $lines[] = '';
                                $lines[] = 'Primeras filas:';

                                foreach ($parsed['preview'] as $row) {
                                    $lines[] = implode(' | ', collect($row)->values()->all());
                                }

                                return implode("\n", $lines);
                            }),
                    ]),
                Step::make('Mapeo')
                    ->description('Define qué columna del CSV se guarda en cada campo.')
                    ->schema([
                        Placeholder::make('mapping_autofill_hint')
                            ->hiddenLabel()
                            ->content(function (Get $get): string {
                                $parsed = self::parseCsvState(
                                    csvSource: (string) ($get('csv_source') ?? 'upload'),
                                    csvState: $get('csv_file'),
                                    curatorMediaId: $get('curator_media_id'),
                                    delimiter: self::normalizeDelimiter((string) $get('delimiter')),
                                    hasHeader: (bool) ($get('has_header') ?? true),
                                );

                                if (($parsed['error'] ?? null) === 'missing_file') {
                                    return 'Sube un CSV en el paso Archivo para aplicar sugerencias de mapeo automático.';
                                }

                                if (filled($parsed['error'] ?? null)) {
                                    return 'No se pudo leer el CSV para sugerencias: '.(string) $parsed['error'];
                                }

                                $mappedSimpleFields = collect([
                                    $get('map_name'),
                                    $get('map_email'),
                                    $get('map_phone'),
                                    $get('map_rut'),
                                    $get('map_comuna'),
                                    $get('map_proyecto'),
                                    $get('map_message'),
                                ])->filter(static fn (mixed $value): bool => filled($value))->count();

                                $customMappingsCount = collect((array) ($get('custom_mappings') ?? []))
                                    ->filter(static fn (array $mapping): bool => filled($mapping['source_column'] ?? null) && filled($mapping['target_field'] ?? null))
                                    ->count();

                                $totalMappings = $mappedSimpleFields + $customMappingsCount;

                                if ($totalMappings === 0) {
                                    return 'No se detectaron sugerencias automáticas para los encabezados actuales. Puedes mapear manualmente.';
                                }

                                return "Sugerencias automáticas aplicadas: {$totalMappings} mapeo(s). Puedes ajustarlos manualmente antes de importar.";
                            })
                            ->columnSpanFull(),
                        Placeholder::make('mapping_sample_table')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::mappingPreviewTable($get))
                            ->columnSpanFull(),
                        Select::make('map_name')
                            ->label('Nombre (obligatorio)')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_name'))
                            ->searchable(),
                        Select::make('map_email')
                            ->label('Email (obligatorio)')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_email'))
                            ->searchable(),
                        Select::make('map_phone')
                            ->label('Teléfono / Celular')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_phone'))
                            ->searchable(),
                        Select::make('map_rut')
                            ->label('RUT')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_rut'))
                            ->searchable(),
                        Select::make('map_comuna')
                            ->label('Comuna')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_comuna'))
                            ->searchable(),
                        Select::make('map_proyecto')
                            ->label('Proyecto')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_proyecto', isProjectField: true))
                            ->searchable(),
                        Select::make('map_message')
                            ->label('Comentario / Mensaje')
                            ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn (Get $get): string => self::mappingHelperText($get, 'map_message'))
                            ->searchable(),
                        Repeater::make('custom_mappings')
                            ->label('Mapeos adicionales')
                            ->schema([
                                Select::make('source_column')
                                    ->label('Columna CSV')
                                    ->options(fn (Get $get): array => self::csvHeaderOptions($get))
                                    ->required()
                                    ->searchable(),
                                Select::make('target_field')
                                    ->label('Campo destino')
                                    ->options(fn (): array => self::targetFieldOptions())
                                    ->required()
                                    ->searchable(),
                            ])
                            ->defaultItems(0)
                            ->columns(2)
                            ->addActionLabel('Agregar mapeo'),
                        Toggle::make('auto_map_unmapped')
                            ->label('Mapear columnas no seleccionadas a campos dinámicos automáticamente')
                            ->default(true),
                    ]),
                Step::make('Opciones')
                    ->description('Configura canal, homologación y sincronización.')
                    ->schema([
                        Select::make('contact_channel_id')
                            ->label('Canal de contacto (obligatorio)')
                            ->options(fn (): array => ContactChannel::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Toggle::make('sync_to_salesforce')
                            ->label('Sincronizar en Salesforce al importar')
                            ->default(true),
                        Toggle::make('homologate_comuna')
                            ->label('Homologar comuna')
                            ->default(true),
                        Toggle::make('homologate_proyecto')
                            ->label('Homologar proyecto')
                            ->default(true),
                    ]),
                Step::make('Confirmación')
                    ->description('Revisa antes de ejecutar la importación.')
                    ->schema([
                        Textarea::make('summary')
                            ->label('Resumen')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function (Get $get): string {
                                $headers = array_keys(self::csvHeaderOptions($get));

                                $lines = [
                                    'Columnas detectadas: '.implode(', ', $headers),
                                    'Canal seleccionado ID: '.(string) ($get('contact_channel_id') ?? '-'),
                                    'Sync Salesforce: '.((bool) ($get('sync_to_salesforce') ?? true) ? 'Si' : 'No'),
                                    'Homologar comuna: '.((bool) ($get('homologate_comuna') ?? true) ? 'Si' : 'No'),
                                    'Homologar proyecto: '.((bool) ($get('homologate_proyecto') ?? true) ? 'Si' : 'No'),
                                    'Auto-map columnas restantes: '.((bool) ($get('auto_map_unmapped') ?? true) ? 'Si' : 'No'),
                                ];

                                return implode("\n", $lines);
                            }),
                    ]),
            ])
            ->action(function (array $data): void {
                $csvSource = (string) ($data['csv_source'] ?? 'upload');

                if ($csvSource === 'upload' && self::resolveCsvFilePath($data['csv_file'] ?? null) === null) {
                    Notification::make()
                        ->title('CSV requerido')
                        ->body('Debes subir un archivo CSV antes de importar.')
                        ->danger()
                        ->send();

                    return;
                }

                if ($csvSource === 'files' && blank($data['curator_media_id'] ?? null)) {
                    Notification::make()
                        ->title('CSV requerido')
                        ->body('Debes elegir un CSV desde Archivos antes de importar.')
                        ->danger()
                        ->send();

                    return;
                }

                $channel = ContactChannel::query()
                    ->whereKey((int) ($data['contact_channel_id'] ?? 0))
                    ->where('is_active', true)
                    ->first();

                if ($channel === null) {
                    Notification::make()
                        ->title('Canal inválido')
                        ->body('Selecciona un canal de contacto activo.')
                        ->danger()
                        ->send();

                    return;
                }

                $parsed = self::parseCsvState(
                    csvSource: $csvSource,
                    csvState: $data['csv_file'] ?? null,
                    curatorMediaId: $data['curator_media_id'] ?? null,
                    delimiter: self::normalizeDelimiter((string) ($data['delimiter'] ?? ',')),
                    hasHeader: (bool) ($data['has_header'] ?? true),
                );

                if (filled($parsed['error'] ?? null)) {
                    Notification::make()
                        ->title('Error al parsear CSV')
                        ->body((string) $parsed['error'])
                        ->danger()
                        ->send();

                    return;
                }

                if ($csvSource === 'upload') {
                    $csvFile = self::resolveCsvFilePath($data['csv_file'] ?? null);

                    if (is_string($csvFile) && $csvFile !== '') {
                        self::storeImportedCsvInCurator($csvFile);
                    }
                }

                $mappings = self::resolveMappings($data);
                $rowMapper = app(ContactCsvRowMapper::class);
                $homologationService = app(ContactTextHomologationService::class);

                $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));
                $syncToSalesforce = (bool) ($data['sync_to_salesforce'] ?? true) && $leadEnabled;

                $created = 0;
                $failed = 0;
                $warnings = 0;
                $errorMessages = [];

                foreach ($parsed['rows'] as $lineIndex => $row) {
                    $mapped = $rowMapper->mapRow(
                        row: $row,
                        mappings: $mappings,
                        autoMapUnmapped: (bool) ($data['auto_map_unmapped'] ?? true),
                    );

                    $fields = $mapped['fields'];

                    if ((bool) ($data['homologate_comuna'] ?? true) || (bool) ($data['homologate_proyecto'] ?? true)) {
                        $homologated = $homologationService->homologate(
                            fields: $fields,
                            homologateComuna: (bool) ($data['homologate_comuna'] ?? true),
                            homologateProyecto: (bool) ($data['homologate_proyecto'] ?? true),
                        );

                        $fields = $homologated['fields'];
                        $warnings += count($homologated['warnings']);
                    }

                    if (blank($fields['comuna'] ?? null) || blank($fields['proyecto'] ?? null)) {
                        $failed++;
                        $errorMessages[] = 'Fila '.($lineIndex + 2).': Comuna y Proyecto son obligatorios.';

                        continue;
                    }

                    $email = trim((string) ($mapped['email'] ?? ''));
                    if ($email !== '' && Validator::make(['email' => $email], ['email' => ['email']])->fails()) {
                        $failed++;
                        $errorMessages[] = 'Fila '.($lineIndex + 2).': email inválido.';

                        continue;
                    }

                    $submission = ContactSubmission::query()->create([
                        'contact_channel_id' => $channel->id,
                        'name' => $mapped['name'],
                        'email' => $email !== '' ? $email : null,
                        'phone' => filled($mapped['phone'] ?? null) ? $mapped['phone'] : null,
                        'rut' => filled($mapped['rut'] ?? null) ? $mapped['rut'] : null,
                        'fields' => $fields,
                        'recipient_email' => $channel->effectiveNotificationEmail(),
                        'ip_address' => Request::ip(),
                        'user_agent' => 'filament-csv-import',
                        'submitted_at' => now(),
                    ]);

                    if ($syncToSalesforce) {
                        CreateSalesforceCaseJob::dispatchSync($submission, 'manual');
                    }

                    $created++;
                }

                $body = "Creados: {$created}. Errores: {$failed}. Warnings: {$warnings}.";

                if ($failed > 0) {
                    $body .= "\n".collect($errorMessages)->take(3)->implode("\n");
                }

                $notification = Notification::make()
                    ->title($failed > 0 ? 'Importación completada con observaciones' : 'Importación completada')
                    ->body($body);

                if ($failed > 0) {
                    $notification->warning()->send();

                    return;
                }

                $notification->success()->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function csvHeaderOptions(Get $get): array
    {
        $parsed = self::parseCsvState(
            csvSource: (string) ($get('csv_source') ?? 'upload'),
            csvState: $get('csv_file'),
            curatorMediaId: $get('curator_media_id'),
            delimiter: self::normalizeDelimiter((string) $get('delimiter')),
            hasHeader: (bool) ($get('has_header') ?? true),
        );

        if (($parsed['error'] ?? null) === 'missing_file') {
            return [];
        }

        if (filled($parsed['error'] ?? null)) {
            return [];
        }

        return collect($parsed['headers'])
            ->mapWithKeys(static fn (string $header): array => [$header => $header])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function targetFieldOptions(): array
    {
        $baseOptions = [
            'skip' => 'No importar',
            'name' => 'Nombre (principal)',
            'email' => 'Email (principal)',
            'phone' => 'Teléfono (principal)',
            'rut' => 'RUT (principal)',
            'fields.comuna' => 'Campo dinámico: Comuna',
            'fields.proyecto' => 'Campo dinámico: Proyecto',
            'fields.mensaje' => 'Campo dinámico: Mensaje',
            'fields.campana' => 'Campo dinámico: Campaña',
            'fields.medio_llegada' => 'Campo dinámico: Medio de llegada',
            'fields.origen_prospecto' => 'Campo dinámico: Origen del prospecto',
        ];

        $dynamicFields = collect(SiteSetting::current()->contact_form_fields ?? [])
            ->filter(static fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(static function (array $field): array {
                $key = trim((string) $field['key']);
                $label = trim((string) ($field['label'] ?? $key));

                return ["fields.{$key}" => "Campo dinámico: {$label}"];
            })
            ->all();

        return collect($baseOptions)->union($dynamicFields)->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{source_column: string, target_field: string}>
     */
    private static function resolveMappings(array $data): array
    {
        $mappings = [];

        $simpleMap = [
            'map_name' => 'name',
            'map_email' => 'email',
            'map_phone' => 'phone',
            'map_rut' => 'rut',
            'map_comuna' => 'fields.comuna',
            'map_proyecto' => 'fields.proyecto',
            'map_message' => 'fields.mensaje',
        ];

        foreach ($simpleMap as $sourceStateKey => $targetField) {
            $sourceColumn = trim((string) ($data[$sourceStateKey] ?? ''));

            if ($sourceColumn === '') {
                continue;
            }

            $mappings[] = [
                'source_column' => $sourceColumn,
                'target_field' => $targetField,
            ];
        }

        foreach ((array) ($data['custom_mappings'] ?? []) as $mapping) {
            $sourceColumn = trim((string) ($mapping['source_column'] ?? ''));
            $targetField = trim((string) ($mapping['target_field'] ?? ''));

            if ($sourceColumn === '' || $targetField === '') {
                continue;
            }

            $mappings[] = [
                'source_column' => $sourceColumn,
                'target_field' => $targetField,
            ];
        }

        return collect($mappings)
            ->unique(static fn (array $mapping): string => $mapping['source_column'].'::'.$mapping['target_field'])
            ->values()
            ->all();
    }

    private static function normalizeDelimiter(string $delimiter): ?string
    {
        $cleaned = trim($delimiter);

        if ($cleaned === '') {
            return null;
        }

        if ($cleaned === '\\t') {
            return "\t";
        }

        return $cleaned;
    }

    private static function mappingHelperText(Get $get, string $mapStatePath, bool $isProjectField = false): string
    {
        $selectedColumn = trim((string) ($get($mapStatePath) ?? ''));

        if ($selectedColumn === '') {
            return 'Selecciona una columna para ver un ejemplo de dato.';
        }

        $sample = self::firstSampleValueForColumn($get, $selectedColumn);

        if ($sample === null) {
            return 'No se encontró un valor de ejemplo para esa columna.';
        }

        $text = 'Ejemplo: '.$sample;

        if ($isProjectField && self::looksLikeSalesforceId($sample)) {
            $text .= ' (detectado como Salesforce ID; se intentará resolver por ID y, si no existe, por nombre).';
        }

        return $text;
    }

    private static function mappingPreviewTable(Get $get): HtmlString
    {
        $rows = [
            ['label' => 'Nombre', 'state' => 'map_name', 'isProject' => false, 'required' => true],
            ['label' => 'Email', 'state' => 'map_email', 'isProject' => false, 'required' => true],
            ['label' => 'Teléfono / Celular', 'state' => 'map_phone', 'isProject' => false, 'required' => false],
            ['label' => 'RUT', 'state' => 'map_rut', 'isProject' => false, 'required' => false],
            ['label' => 'Comuna', 'state' => 'map_comuna', 'isProject' => false, 'required' => false],
            ['label' => 'Proyecto', 'state' => 'map_proyecto', 'isProject' => true, 'required' => false],
            ['label' => 'Comentario / Mensaje', 'state' => 'map_message', 'isProject' => false, 'required' => false],
        ];

        $tableRows = '';

        foreach ($rows as $row) {
            $selectedColumn = trim((string) ($get($row['state']) ?? ''));

            if ($selectedColumn === '') {
                continue;
            }

            $sample = self::firstSampleValueForColumn($get, $selectedColumn) ?? '-';
            $note = '';
            $requiredLabel = ($row['required'] ?? false) ? 'Obligatorio' : 'Opcional';

            if (($row['isProject'] ?? false) && self::looksLikeSalesforceId($sample)) {
                $note = 'Salesforce ID detectado (ID -> nombre fallback).';
            }

            $tableRows .= sprintf(
                '<tr><td style="padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.08);">%s</td><td style="padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.08);">%s</td><td style="padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.08);">%s</td><td style="padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.08);">%s</td><td style="padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.08);">%s</td></tr>',
                e((string) $row['label']),
                e($requiredLabel),
                e($selectedColumn),
                e($sample),
                e($note),
            );
        }

        if ($tableRows === '') {
            return new HtmlString('<div style="opacity:.8;">Selecciona columnas para ver una tabla resumen de mapeo con valores de ejemplo.</div><div style="margin-top:6px; font-size:.85rem; opacity:.85;">Campos obligatorios: Nombre y Email. El Canal de contacto también es obligatorio y se define en el paso Opciones.</div>');
        }

        return new HtmlString(
            '<div style="margin:4px 0 10px;">'
            .'<div style="font-size:.92rem; margin-bottom:6px; opacity:.9;">Resumen rápido de mapeo (con ejemplo por columna)</div>'
            .'<div style="font-size:.85rem; margin-bottom:8px; opacity:.85;">Campos obligatorios: Nombre y Email. El Canal de contacto también es obligatorio y se define en el paso Opciones.</div>'
            .'<table style="width:100%; border-collapse:collapse; font-size:.88rem;">'
            .'<thead><tr>'
            .'<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Campo</th>'
            .'<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Tipo</th>'
            .'<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Columna CSV</th>'
            .'<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Ejemplo</th>'
            .'<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Nota</th>'
            .'</tr></thead>'
            .'<tbody>'.$tableRows.'</tbody>'
            .'</table></div>'
        );
    }

    private static function firstSampleValueForColumn(Get $get, string $column): ?string
    {
        $parsed = self::parseCsvState(
            csvSource: (string) ($get('csv_source') ?? 'upload'),
            csvState: $get('csv_file'),
            curatorMediaId: $get('curator_media_id'),
            delimiter: self::normalizeDelimiter((string) $get('delimiter')),
            hasHeader: (bool) ($get('has_header') ?? true),
        );

        if (filled($parsed['error'] ?? null)) {
            return null;
        }

        foreach ($parsed['rows'] as $row) {
            $value = trim((string) ($row[$column] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function looksLikeSalesforceId(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]{15,18}$/', trim($value)) === 1;
    }

    private static function prefillSuggestedMappings(Get $get, Set $set): void
    {
        $parsed = self::parseCsvState(
            csvSource: (string) ($get('csv_source') ?? 'upload'),
            csvState: $get('csv_file'),
            curatorMediaId: $get('curator_media_id'),
            delimiter: self::normalizeDelimiter((string) $get('delimiter')),
            hasHeader: (bool) ($get('has_header') ?? true),
        );

        if (($parsed['error'] ?? null) === 'missing_file') {
            return;
        }

        if (filled($parsed['error'] ?? null)) {
            return;
        }

        $suggestedMappings = app(ContactCsvRowMapper::class)->buildSuggestedMappings($parsed['headers']);

        $stateMapByTarget = [
            'name' => 'map_name',
            'email' => 'map_email',
            'phone' => 'map_phone',
            'rut' => 'map_rut',
            'fields.comuna' => 'map_comuna',
            'fields.proyecto' => 'map_proyecto',
            'fields.mensaje' => 'map_message',
        ];

        foreach ($suggestedMappings as $suggestedMapping) {
            $target = (string) ($suggestedMapping['target_field'] ?? '');
            $source = (string) ($suggestedMapping['source_column'] ?? '');

            if ($target === '' || $source === '') {
                continue;
            }

            $statePath = $stateMapByTarget[$target] ?? null;

            if ($statePath === null) {
                continue;
            }

            if (filled($get($statePath))) {
                continue;
            }

            $set($statePath, $source);
        }

        $existingCustomMappings = collect((array) ($get('custom_mappings') ?? []));

        if ($existingCustomMappings->isNotEmpty()) {
            return;
        }

        $extraSuggestedTargets = [
            'fields.medio_llegada',
            'fields.origen_prospecto',
            'fields.campana',
        ];

        $customMappings = collect($suggestedMappings)
            ->filter(static fn (array $mapping): bool => in_array((string) ($mapping['target_field'] ?? ''), $extraSuggestedTargets, true))
            ->map(static fn (array $mapping): array => [
                'source_column' => (string) ($mapping['source_column'] ?? ''),
                'target_field' => (string) ($mapping['target_field'] ?? ''),
            ])
            ->filter(static fn (array $mapping): bool => filled($mapping['source_column']) && filled($mapping['target_field']))
            ->values()
            ->all();

        if ($customMappings !== []) {
            $set('custom_mappings', $customMappings);
        }
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>, preview: array<int, array<string, string>>, delimiter: string, total_rows: int, error: string|null}
     */
    private static function parseCsvState(string $csvSource, mixed $csvState, mixed $curatorMediaId, ?string $delimiter, bool $hasHeader): array
    {
        if ($csvSource === 'files') {
            $media = Media::query()->find((int) $curatorMediaId);

            if ($media === null) {
                return [
                    'headers' => [],
                    'rows' => [],
                    'preview' => [],
                    'delimiter' => $delimiter ?? ',',
                    'total_rows' => 0,
                    'error' => 'missing_file',
                ];
            }

            return app(ContactCsvParser::class)->parseFile(
                filePath: (string) $media->path,
                delimiter: $delimiter,
                hasHeader: $hasHeader,
                disk: (string) $media->disk,
            );
        }

        if ($csvState instanceof TemporaryUploadedFile) {
            $realPath = $csvState->getRealPath();

            if (is_string($realPath) && $realPath !== '' && file_exists($realPath)) {
                $content = file_get_contents($realPath);

                if (is_string($content) && $content !== '') {
                    return app(ContactCsvParser::class)->parseContent(
                        content: $content,
                        delimiter: $delimiter,
                        hasHeader: $hasHeader,
                    );
                }
            }
        }

        $csvFile = self::resolveCsvFilePath($csvState);

        if ($csvFile === null) {
            return [
                'headers' => [],
                'rows' => [],
                'preview' => [],
                'delimiter' => $delimiter ?? ',',
                'total_rows' => 0,
                'error' => 'missing_file',
            ];
        }

        return app(ContactCsvParser::class)->parseFile(
            filePath: $csvFile,
            delimiter: $delimiter,
            hasHeader: $hasHeader,
        );
    }

    /**
     * @return array<int, string>
     */
    private static function csvMediaOptions(): array
    {
        return Media::query()
            ->whereIn('ext', ['csv', 'txt'])
            ->where('directory', 'like', 'imports/contact-submissions%')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'name', 'ext', 'directory', 'path', 'size', 'created_at'])
            ->mapWithKeys(static function (Media $media): array {
                $label = trim((string) ($media->name ?? 'archivo'));
                $ext = trim((string) ($media->ext ?? 'csv'));
                $directory = trim((string) ($media->directory ?? ''));
                $size = self::formatFileSize((int) ($media->size ?? 0));
                $createdAt = $media->created_at?->format('d/m/Y H:i') ?? '-';

                if ($directory !== '') {
                    $label .= ".{$ext} ({$directory})";
                } else {
                    $label .= ".{$ext}";
                }

                $label .= " - {$size} - {$createdAt}";

                return [$media->id => $label];
            })
            ->all();
    }

    private static function formatFileSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 2).' MB';
    }

    private static function resolveCsvFilePath(mixed $state): ?string
    {
        if (is_string($state)) {
            $path = trim($state);

            return $path !== '' ? $path : null;
        }

        if (is_array($state)) {
            foreach ($state as $value) {
                $resolved = self::resolveCsvFilePath($value);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private static function storeImportedCsvInCurator(string $sourcePath): void
    {
        if (! Storage::disk('local')->exists($sourcePath)) {
            return;
        }

        $directory = 'imports/contact-submissions';
        $filename = basename($sourcePath);
        $targetPath = trim($directory.'/'.$filename, '/');

        if (! Storage::disk('curator')->exists($targetPath)) {
            Storage::disk('curator')->put(
                $targetPath,
                (string) Storage::disk('local')->get($sourcePath),
            );
        }

        $existingMedia = Media::query()
            ->where('disk', 'curator')
            ->where('path', $targetPath)
            ->first();

        if ($existingMedia !== null) {
            return;
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $name = (string) pathinfo($filename, PATHINFO_FILENAME);

        Media::query()->create([
            'disk' => 'curator',
            'directory' => $directory,
            'visibility' => 'public',
            'name' => $name,
            'path' => $targetPath,
            'size' => Storage::disk('curator')->size($targetPath),
            'type' => 'text/csv',
            'ext' => $extension !== '' ? $extension : 'csv',
        ]);
    }
}
