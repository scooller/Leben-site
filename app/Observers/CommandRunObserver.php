<?php

namespace App\Observers;

use App\Support\BusinessActivityLogger;
use BinaryBuilds\CommandRunner\Models\CommandRun;

class CommandRunObserver
{
    public function created(CommandRun $commandRun): void
    {
        BusinessActivityLogger::logCommandRunnerStarted($commandRun);
    }

    public function updated(CommandRun $commandRun): void
    {
        if (! $commandRun->wasChanged('completed_at') && ! $commandRun->wasChanged('killed_at')) {
            return;
        }

        BusinessActivityLogger::logCommandRunnerFinished($commandRun);
    }
}
