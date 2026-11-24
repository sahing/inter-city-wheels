<?php

use App\Services\BiddingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bidding:close-expired', function (BiddingService $bidding) {
    $closed = $bidding->closeDueSessions();
    $this->info("Closed {$closed} expired bidding session(s).");
})->purpose('Close driver auctions whose deadline has passed');

// Safety net: the request path also closes an auction lazily, so a missed cron
// run can never leave an expired auction accepting bids.
Schedule::command('bidding:close-expired')->everyMinute()->withoutOverlapping();
