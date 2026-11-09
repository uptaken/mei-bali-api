<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Orders move to Berlangsung on their first day and to Selesai after their last day.
Schedule::command('orders:advance-status')->dailyAt('00:05');
