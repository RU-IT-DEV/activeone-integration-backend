<?php

namespace App\Dispatchers;

use App\Helper\CloudTasksHelper;
use Carbon\Carbon;

class JobDispatcher
{
    public static function dispatch (object $job, ?Carbon $runTime = null): void
    {
        $environment = config('app.env');
        if (in_array($environment, ["local", "development"])) {
            if ($runTime && $runTime->isFuture()) {
                dispatch($job)->delay($runTime);
                return;
            }

            dispatch($job);
            return;
        }

        CloudTasksHelper::dispatchJob($job, $runTime);
    }
}
