<?php

use App\Services\Tokens\SessionEnforcer;
use App\Services\Tokens\TokenService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Expire/exhaust sessions. Runs every minute; the Python service also enforces expiry locally.
Schedule::call(fn () => app(SessionEnforcer::class)->sweep())
    ->name('nws:sweep-sessions')->everyMinute()->withoutOverlapping();

Artisan::command('nws:sweep', function (SessionEnforcer $enforcer) {
    $this->info('Settled '.$enforcer->sweep().' session(s).');
})->purpose('Expire/exhaust tokens and close their sessions');

Artisan::command('nws:purge-voucher-codes {days=30}', function (TokenService $tokens, int $days) {
    $this->info('Purged stored voucher codes: '.$tokens->purgeStoredCodes($days));
})->purpose('Delete recoverable (encrypted) voucher codes older than N days');

Schedule::command('nws:purge-voucher-codes 30')->daily();
