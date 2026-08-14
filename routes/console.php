<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('demo:about', function (): void {
    $this->comment('PromoWallet demo');
})->purpose('Show the application name');
