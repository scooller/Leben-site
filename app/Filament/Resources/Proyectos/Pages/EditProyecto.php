<?php

namespace App\Filament\Resources\Proyectos\Pages;

use App\Filament\Resources\ProyectoResource;
use App\Models\ProjectDetail;
use App\Models\Proyecto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProyecto extends EditRecord
{
    protected static string $resource = ProyectoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prefillWebInfo')
                ->label('Pre-rellenar Ficha Web')
                ->icon('heroicon-m-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Pre-rellenar Ficha Web desde Salesforce')
                ->modalDescription('¿Deseas completar automáticamente los campos vacíos de la Ficha Web con los datos oficiales de Salesforce de este proyecto?')
                ->action(function () {
                    /** @var Proyecto $record */
                    $record = $this->record;
                    $defaults = ProjectDetail::getDefaultsFromProyecto($record);

                    $detail = $record->detail ?? new ProjectDetail(['project_id' => $record->id]);

                    foreach ($defaults as $key => $val) {
                        if (blank($detail->{$key})) {
                            $detail->{$key} = $val;
                        }
                    }

                    $detail->save();

                    $this->fillForm();

                    Notification::make()
                        ->title('Ficha Web pre-rellenada exitosamente con datos de Salesforce')
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Proyecto $record */
        $record = $this->record;
        $defaults = ProjectDetail::getDefaultsFromProyecto($record);

        if (! isset($data['detail']) || ! is_array($data['detail'])) {
            $data['detail'] = [];
        }

        foreach ($defaults as $key => $defaultValue) {
            if (! array_key_exists($key, $data['detail']) || blank($data['detail'][$key])) {
                $data['detail'][$key] = $defaultValue;
            }
        }

        return $data;
    }
}
