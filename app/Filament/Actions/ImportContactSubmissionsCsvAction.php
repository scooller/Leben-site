<?php

namespace App\Filament\Actions;

use App\Filament\Pages\ContactImportProgress;
use App\Jobs\RunContactCsvImportJob;
use App\Models\ContactChannel;
use App\Models\SiteSetting;
use App\Services\ContactImport\ContactCsvParser;
use App\Services\ContactImport\ContactCsvRowMapper;
use App\Services\ContactImport\ContactImportProgressTracker;
use Awcodes\Curator\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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
                    ->description('Selecciona canal, sube el archivo CSV y configura cómo se leerá.')
                    ->schema([
                        Select::make('contact_channel_id')
                            ->label('Canal de contacto (obligatorio)')
                            ->options(fn(): array => ContactChannel::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::sanitizeMappingsForSelectedChannel($get, $set);
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->validationMessages([
                                'required' => 'Selecciona un canal de contacto para la importación.',
                            ])
                            ->required(),
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
                            ->validationMessages([
                                'required' => 'Selecciona el origen del archivo CSV.',
                            ])
                            ->required(),
                        FileUpload::make('csv_file')
                            ->label('Archivo CSV')
                            ->disk('local')
                            ->directory('imports/contact-submissions')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->visible(fn(Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'upload')
                            ->validationMessages([
                                'required' => 'Debes subir un archivo CSV para continuar.',
                            ])
                            ->required(fn(Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'upload')
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->live(),
                        Select::make('curator_media_id')
                            ->label('Archivo CSV desde Archivos')
                            ->options(fn(): array => self::csvMediaOptions())
                            ->searchable()
                            ->preload()
                            ->visible(fn(Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'files')
                            ->validationMessages([
                                'required' => 'Debes elegir un archivo CSV desde Archivos para continuar.',
                            ])
                            ->required(fn(Get $get): bool => (string) ($get('csv_source') ?? 'upload') === 'files')
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
                            ->validationMessages([
                                'required' => 'Selecciona el delimitador del CSV.',
                            ])
                            ->required(),
                        Toggle::make('has_header')
                            ->label('El CSV contiene encabezados en la primera fila')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::prefillSuggestedMappings($get, $set);
                            })
                            ->default(true),
                    ]),
                Step::make('Mapeo')
                    ->description('Define qué columna del CSV se guarda en cada campo del canal seleccionado.')
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
                                    return 'No se pudo leer el CSV para sugerencias: ' . (string) $parsed['error'];
                                }

                                $mappedSimpleFields = collect([
                                    $get('map_name'),
                                    $get('map_email'),
                                    $get('map_phone'),
                                    $get('map_rut'),
                                    $get('map_comuna'),
                                    $get('map_proyecto'),
                                    $get('map_message'),
                                ])->filter(static fn(mixed $value): bool => filled($value))->count();

                                $customMappingsCount = collect((array) ($get('custom_mappings') ?? []))
                                    ->filter(static fn(array $mapping): bool => filled($mapping['source_column'] ?? null) && filled($mapping['target_field'] ?? null))
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
                            ->content(fn(Get $get): HtmlString => self::mappingPreviewTable($get))
                            ->columnSpanFull(),
                        Select::make('map_name')
                            ->label('Nombre (obligatorio)')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_name'))
                            ->searchable(),
                        Select::make('map_email')
                            ->label('Email (obligatorio)')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_email'))
                            ->searchable(),
                        Select::make('map_phone')
                            ->label('Teléfono / Celular')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_phone'))
                            ->searchable(),
                        Select::make('map_rut')
                            ->label('RUT')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_rut'))
                            ->searchable(),
                        Select::make('map_comuna')
                            ->label('Comuna (obligatorio)')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_comuna'))
                            ->validationMessages([
                                'required' => 'Selecciona la columna del CSV que corresponde a Comuna.',
                            ])
                            ->required()
                            ->searchable(),
                        Select::make('map_proyecto')
                            ->label('Proyecto (obligatorio)')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_proyecto', isProjectField: true))
                            ->validationMessages([
                                'required' => 'Selecciona la columna del CSV que corresponde a Proyecto.',
                            ])
                            ->required()
                            ->searchable(),
                        Select::make('map_message')
                            ->label('Comentario / Mensaje')
                            ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                            ->helperText(fn(Get $get): string => self::mappingHelperText($get, 'map_message'))
                            ->searchable(),
                        Repeater::make('custom_mappings')
                            ->label('Mapeos adicionales')
                            ->schema([
                                Select::make('source_column')
                                    ->label('Columna CSV')
                                    ->options(fn(Get $get): array => self::csvHeaderOptions($get))
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona una columna CSV para este mapeo adicional.',
                                        'in' => 'La columna seleccionada ya no existe en el CSV actual. Vuelve a elegir la columna.',
                                    ])
                                    ->searchable(),
                                Select::make('target_field')
                                    ->label('Campo destino')
                                    ->options(fn(Get $get): array => self::targetFieldOptions($get))
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona un campo destino para este mapeo adicional.',
                                        'in' => 'El campo destino seleccionado ya no es válido. Vuelve a elegir el campo destino.',
                                    ])
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
                    ->description('Configura homologación y sincronización.')
                    ->schema([
                        Toggle::make('sync_to_salesforce')
                            ->label('Sincronizar en Salesforce al importar')
                            ->live()
                            ->default(true),
                        Toggle::make('dry_run')
                            ->label('Dry-run / simular antes de importar')
                            ->helperText('Valida mapeos, homologaciones y errores fila por fila sin crear contactos ni sincronizar con Salesforce.')
                            ->live()
                            ->default(false),
                        Toggle::make('homologate_comuna')
                            ->label('Homologar comuna')
                            ->live()
                            ->default(true),
                        Toggle::make('homologate_proyecto')
                            ->label('Homologar proyecto')
                            ->live()
                            ->default(true),
                    ]),
                Step::make('Confirmación')
                    ->description('Revisa antes de ejecutar la importación.')
                    ->schema([
                        Placeholder::make('summary')
                            ->label('Resumen')
                            ->content(fn(Get $get): HtmlString => self::summaryPreview($get))
                            ->columnSpanFull(),
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
                $dryRun = (bool) ($data['dry_run'] ?? false);

                $leadEnabled = (bool) config('services.salesforce.lead_enabled', config('services.salesforce.case_enabled', false));
                $syncToSalesforce = (bool) ($data['sync_to_salesforce'] ?? true) && $leadEnabled;

                $importId = (string) Str::uuid();

                app(ContactImportProgressTracker::class)->initialize(
                    importId: $importId,
                    totalRows: count($parsed['rows']),
                    channelName: (string) $channel->name,
                    syncToSalesforce: $syncToSalesforce,
                    dryRun: $dryRun,
                );

                app(ContactImportProgressTracker::class)->addLog(
                    importId: $importId,
                    message: $dryRun
                        ? 'Simulación iniciada. Se validarán las filas sin crear contactos.'
                        : 'Importación iniciada. Se procesarán las filas y se crearán contactos válidos.',
                );

                // dispatchSync ejecuta el job en el mismo request (sin necesitar queue worker),
                // preserva el usuario autenticado como causer en los activity logs.
                RunContactCsvImportJob::dispatchSync(
                    importId: $importId,
                    rows: $parsed['rows'],
                    mappings: $mappings,
                    contactChannelId: (int) $channel->id,
                    autoMapUnmapped: (bool) ($data['auto_map_unmapped'] ?? true),
                    homologateComuna: (bool) ($data['homologate_comuna'] ?? true),
                    homologateProyecto: (bool) ($data['homologate_proyecto'] ?? true),
                    syncToSalesforce: $syncToSalesforce,
                    dryRun: $dryRun,
                    hasHeader: (bool) ($data['has_header'] ?? true),
                    ipAddress: Request::ip(),
                    userAgent: 'filament-csv-import',
                );

                $progressUrl = ContactImportProgress::getUrl(['import' => $importId]);

                Notification::make()
                    ->title($dryRun ? 'Simulación finalizada' : 'Importación finalizada')
                    ->body($dryRun
                        ? 'La simulación terminó. Revisa el detalle antes de ejecutar la importación real.'
                        : 'La importación terminó. Puedes revisar el detalle del proceso y los resultados.')
                    ->success()
                    ->persistent()
                    ->actions([
                        Action::make('viewImportProgress')
                            ->label('Ver progreso en vivo')
                            ->button()
                            ->url($progressUrl, shouldOpenInNewTab: true),
                    ])
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function csvHeaderOptions(Get $get): array
    {
        $parsed = self::parseCsvState(
            csvSource: (string) self::resolveWizardStateValue($get, 'csv_source', 'upload'),
            csvState: self::resolveWizardStateValue($get, 'csv_file'),
            curatorMediaId: self::resolveWizardStateValue($get, 'curator_media_id'),
            delimiter: self::normalizeDelimiter((string) self::resolveWizardStateValue($get, 'delimiter', '')),
            hasHeader: (bool) self::resolveWizardStateValue($get, 'has_header', true),
        );

        if (($parsed['error'] ?? null) === 'missing_file') {
            return [];
        }

        if (filled($parsed['error'] ?? null)) {
            return [];
        }

        return collect($parsed['headers'])
            ->mapWithKeys(static fn(string $header): array => [$header => $header])
            ->all();
    }

    private static function resolveWizardStateValue(Get $get, string $path, mixed $default = null): mixed
    {
        for ($levels = 0; $levels <= 10; $levels++) {
            $prefix = str_repeat('../', $levels);
            $candidate = $get($prefix . $path);

            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return $default;
    }

    private static function summaryPreview(Get $get): HtmlString
    {
        $headers = array_keys(self::csvHeaderOptions($get));
        $selectedChannelId = (int) self::resolveWizardStateValue($get, 'contact_channel_id', 0);
        $selectedChannel = $selectedChannelId > 0
            ? ContactChannel::query()->find($selectedChannelId)
            : null;
        $selectedChannelText = $selectedChannel !== null
            ? $selectedChannel->name . ' (ID: ' . $selectedChannel->id . ')'
            : ($selectedChannelId > 0 ? 'ID: ' . $selectedChannelId : '-');

        $syncToSalesforce = (bool) self::resolveWizardStateValue($get, 'sync_to_salesforce', true);
        $dryRun = (bool) self::resolveWizardStateValue($get, 'dry_run', false);
        $homologateComuna = (bool) self::resolveWizardStateValue($get, 'homologate_comuna', true);
        $homologateProyecto = (bool) self::resolveWizardStateValue($get, 'homologate_proyecto', true);
        $autoMapUnmapped = (bool) self::resolveWizardStateValue($get, 'auto_map_unmapped', true);

        $lines = [
            'Columnas detectadas: ' . implode(', ', $headers),
            'Canal seleccionado: ' . $selectedChannelText,
            'Modo simulación (dry-run): ' . ($dryRun ? 'Si' : 'No'),
            'Sync Salesforce: ' . ($syncToSalesforce ? 'Si' : 'No'),
            'Homologar comuna: ' . ($homologateComuna ? 'Si' : 'No'),
            'Homologar proyecto: ' . ($homologateProyecto ? 'Si' : 'No'),
            'Auto-map columnas restantes: ' . ($autoMapUnmapped ? 'Si' : 'No'),
        ];

        return new HtmlString(
            '<pre style="margin:0; padding:12px 14px; border-radius:12px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.03); white-space:pre-wrap; font-size:.92rem; line-height:1.5;">'
                . e(implode("\n", $lines))
                . '</pre>'
        );
    }

    /**
     * @return array<string, string>
     */
    private static function targetFieldOptions(Get $get): array
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
            'fields.rango_renta' => 'Campo dinámico: Rango renta',
            'fields.codeudor' => 'Campo dinámico: Codeudor / Complementa renta',
            'fields.campana' => 'Campo dinámico: Campaña',
            'fields.medio_llegada' => 'Campo dinámico: Medio de llegada',
            'fields.origen_prospecto' => 'Campo dinámico: Origen del prospecto',
        ];

        $selectedChannelId = (int) self::resolveWizardStateValue($get, 'contact_channel_id', 0);

        $selectedChannel = $selectedChannelId > 0
            ? ContactChannel::query()
            ->whereKey($selectedChannelId)
            ->where('is_active', true)
            ->first()
            : null;

        $dynamicFieldDefinitions = $selectedChannel?->effectiveFormFields();

        if (! is_array($dynamicFieldDefinitions)) {
            $dynamicFieldDefinitions = SiteSetting::current()->contact_form_fields ?? [];
        }

        $dynamicFields = collect($dynamicFieldDefinitions)
            ->filter(static fn(mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
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
            ->unique(static fn(array $mapping): string => $mapping['source_column'] . '::' . $mapping['target_field'])
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

        $text = 'Ejemplo: ' . $sample;

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
            ['label' => 'Comuna', 'state' => 'map_comuna', 'isProject' => false, 'required' => true],
            ['label' => 'Proyecto', 'state' => 'map_proyecto', 'isProject' => true, 'required' => true],
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
            return new HtmlString('<div style="opacity:.8;">Selecciona columnas para ver una tabla resumen de mapeo con valores de ejemplo.</div><div style="margin-top:6px; font-size:.85rem; opacity:.85;">Campos obligatorios: Nombre, Email, Comuna y Proyecto. El Canal de contacto también es obligatorio y se define en el paso Archivo.</div>');
        }

        return new HtmlString(
            '<div style="margin:4px 0 10px;">'
                . '<div style="font-size:.92rem; margin-bottom:6px; opacity:.9;">Resumen rápido de mapeo (con ejemplo por columna)</div>'
                . '<div style="font-size:.85rem; margin-bottom:8px; opacity:.85;">Campos obligatorios: Nombre, Email, Comuna y Proyecto. El Canal de contacto también es obligatorio y se define en el paso Archivo.</div>'
                . '<table style="width:100%; border-collapse:collapse; font-size:.88rem;">'
                . '<thead><tr>'
                . '<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Campo</th>'
                . '<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Tipo</th>'
                . '<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Columna CSV</th>'
                . '<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Ejemplo</th>'
                . '<th style="text-align:left; padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.18);">Nota</th>'
                . '</tr></thead>'
                . '<tbody>' . $tableRows . '</tbody>'
                . '</table></div>'
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

        $availableTargets = array_fill_keys(array_keys(self::targetFieldOptions($get)), true);

        $extraSuggestedTargets = [
            'fields.rango_renta',
            'fields.codeudor',
            'fields.medio_llegada',
            'fields.origen_prospecto',
            'fields.campana',
        ];

        $customMappings = collect($suggestedMappings)
            ->filter(static fn(array $mapping): bool => in_array((string) ($mapping['target_field'] ?? ''), $extraSuggestedTargets, true))
            ->filter(static fn(array $mapping): bool => isset($availableTargets[(string) ($mapping['target_field'] ?? '')]))
            ->map(static fn(array $mapping): array => [
                'source_column' => (string) ($mapping['source_column'] ?? ''),
                'target_field' => (string) ($mapping['target_field'] ?? ''),
            ])
            ->filter(static fn(array $mapping): bool => filled($mapping['source_column']) && filled($mapping['target_field']))
            ->values()
            ->all();

        if ($customMappings !== []) {
            $set('custom_mappings', $customMappings);
        }
    }

    private static function sanitizeMappingsForSelectedChannel(Get $get, Set $set): void
    {
        $allowedTargets = array_fill_keys(array_keys(self::targetFieldOptions($get)), true);
        $customMappings = (array) ($get('custom_mappings') ?? []);

        $sanitizedCustomMappings = collect($customMappings)
            ->map(static function (array $mapping) use ($allowedTargets): ?array {
                $sourceColumn = trim((string) ($mapping['source_column'] ?? ''));
                $targetField = trim((string) ($mapping['target_field'] ?? ''));

                if ($sourceColumn === '' && $targetField === '') {
                    return null;
                }

                if ($targetField !== '' && ! isset($allowedTargets[$targetField])) {
                    return null;
                }

                return [
                    'source_column' => $sourceColumn,
                    'target_field' => $targetField,
                ];
            })
            ->filter(static fn(?array $mapping): bool => is_array($mapping))
            ->values()
            ->all();

        $set('custom_mappings', $sanitizedCustomMappings);
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
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 2) . ' MB';
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
        $targetPath = trim($directory . '/' . $filename, '/');

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
