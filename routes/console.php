<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ensure Laravel dev server binds to 0.0.0.0:8000 so mobile devices, Waydroid, and emulators can connect
if (class_exists(\Illuminate\Foundation\DevCommands::class)) {
    \Illuminate\Foundation\DevCommands::artisan('serve --host=0.0.0.0 --port=8000', 'server');
}
