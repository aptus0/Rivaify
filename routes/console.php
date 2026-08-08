<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Commerce\Jobs\Inventory\ReleaseExpiredReservations;
use Modules\Commerce\Jobs\Notification\DispatchPendingOrderNotifications;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ReleaseExpiredReservations)->everyMinute()->withoutOverlapping();
Schedule::job(new DispatchPendingOrderNotifications)->everyMinute()->withoutOverlapping();
