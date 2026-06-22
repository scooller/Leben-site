<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs;

use AlizHarb\ActivityLog\Models\Activity;
use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource as BaseActivityLogResource;
use AlizHarb\ActivityLog\Resources\ActivityLogs\Tables\ActivityLogTable;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\ActivityLogs\Pages\ViewActivityLog;
use App\Filament\Resources\ActivityLogs\Schemas\ActivityLogInfolist;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;

class ActivityLogResource extends BaseActivityLogResource
{
	public static function table(Table $table): Table
	{
		$table = ActivityLogTable::configure($table);

		foreach ($table->getHeaderActions() as $action) {
			if ($action->getName() === 'prune') {
				$action->action(function (array $data) {
					$count = Activity::query()
						->whereDate('created_at', '<=', $data['prune_until'])
						->delete();

					$msj = $count > 0 ? __('filament-activity-log::activity.action.prune.success', ['count' => $count]) : 'No se han podido borrar';
					Notification::make()
						->success()
						->title($msj)
						->send();

					Log::info('Intento de borrado de logs activity con fecha:' . $data['prune_until']);
				});
			} else {
				// agregar log si la accion es distinta
				Log::info('Accion erronea se esperaba prune y llego ' . $action->getName());
			}
		}

		return $table;
	}

	public static function infolist(Schema $schema): Schema
	{
		return ActivityLogInfolist::configure($schema);
	}

	public static function getPages(): array
	{
		return [
			'index' => ListActivityLogs::route('/'),
			'view' => ViewActivityLog::route('/{record}'),
		];
	}
}
