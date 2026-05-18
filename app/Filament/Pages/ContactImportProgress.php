<?php

namespace App\Filament\Pages;

use App\Services\ContactImport\ContactImportProgressTracker;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Request;

class ContactImportProgress extends Page
{
    protected static ?string $title = 'Progreso de Importación';

    protected static ?string $navigationLabel = 'Progreso Importación';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.contact-import-progress';

    public ?string $importId = null;

    /**
     * @var array<string, mixed>
     */
    public array $snapshot = [];

    public function mount(): void
    {
        $queryImport = Request::query('import', '');
        $this->importId = is_scalar($queryImport) ? trim((string) $queryImport) : '';
        $this->refreshProgress();
    }

    public function refreshProgress(): void
    {
        if ($this->importId === null || $this->importId === '') {
            $this->snapshot = [
                'status' => 'not_found',
                'logs' => [],
            ];

            return;
        }

        $this->snapshot = app(ContactImportProgressTracker::class)->snapshot($this->importId);
    }
}
