<?php

use App\Models\GeoPlaceCache;
use App\Models\GeoRouteCache;
use App\Services\BiddingService;
use App\Services\GeoCacheService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('geo:prune-cache', function (GeoCacheService $cache) {
    $pruned = $cache->prune();
    $this->info("Pruned {$pruned['routes']} expired route(s) and {$pruned['places']} expired place(s).");
})->purpose('Delete expired OpenStreetMap route/place cache rows');

Artisan::command('geo:cache-stats', function () {
    $this->table(
        ['Store', 'Rows', 'Expired', 'Hits'],
        [
            [
                'routes',
                GeoRouteCache::count(),
                GeoRouteCache::whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
                (int) GeoRouteCache::sum('hits'),
            ],
            [
                'places',
                GeoPlaceCache::count(),
                GeoPlaceCache::whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
                (int) GeoPlaceCache::sum('hits'),
            ],
        ]
    );
})->purpose('Show how many OSM routes and places are cached');

Artisan::command('firebase:check', function () {
    $apiKey = trim((string) config('services.firebase.api_key'));
    $projectId = trim((string) config('services.firebase.project_id'));
    $authDomain = trim((string) config('services.firebase.auth_domain'));

    if ($apiKey === '') {
        $this->error('FIREBASE_API_KEY is not set.');

        return 1;
    }

    // 1. Which project does this key belong to, and which hosts may sign in?
    try {
        $configRes = Http::timeout(6)->acceptJson()->get(
            'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getProjectConfig',
            ['key' => $apiKey]
        );
    } catch (Throwable $e) {
        $this->error('Could not reach Google: '.$e->getMessage());

        return 1;
    }

    if (! $configRes->successful()) {
        $this->error('Google rejected the API key (HTTP '.$configRes->status().'). Check FIREBASE_API_KEY.');

        return 1;
    }

    $config = $configRes->json() ?: [];
    $domains = array_values((array) ($config['authorizedDomains'] ?? []));
    $number = (string) ($config['projectId'] ?? $projectId);

    $this->table(
        ['Setting', 'Value'],
        [
            ['FIREBASE_PROJECT_ID', $projectId !== '' ? $projectId : '(empty)'],
            ['Key belongs to project', $number !== '' ? $number : '(unknown)'],
            ['FIREBASE_AUTH_DOMAIN', $authDomain !== '' ? $authDomain : '(empty)'],
            ['Authorized domains', $domains === [] ? '(none)' : implode(', ', $domains)],
        ]
    );

    $hosts = array_values(array_unique(array_filter([
        parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        'localhost',
        '127.0.0.1',
    ])));

    $failed = false;

    foreach ($hosts as $host) {
        if (in_array($host, $domains, true)) {
            $this->line(" <info>OK</info>   {$host} is authorized");
        } else {
            $failed = true;
            $this->line(" <error>FAIL</error> {$host} is NOT authorized — add it under Authentication → Settings → Authorized domains");
        }
    }

    // 2. Is the Google provider enabled, and which OAuth client does it use?
    //    continueUri is the Firebase auth handler, i.e. the redirect_uri the
    //    real popup hands to Google.
    $handlerUrl = $authDomain !== '' ? "https://{$authDomain}/__/auth/handler" : (string) config('app.url', 'http://localhost');

    $authRes = null;

    try {
        $authRes = Http::timeout(6)->acceptJson()->post(
            'https://www.googleapis.com/identitytoolkit/v3/relyingparty/createAuthUri?key='.urlencode($apiKey),
            [
                'providerId' => 'google.com',
                'continueUri' => $handlerUrl,
                'registered' => true,
            ]
        );
    } catch (Throwable $e) {
        $this->warn('Could not test the Google provider: '.$e->getMessage());
    }

    if ($authRes !== null && ! $authRes->successful()) {
        $failed = true;
        $message = (string) ($authRes->json('error.message') ?? ('HTTP '.$authRes->status()));
        $this->line(' <error>FAIL</error> Google provider: '.$message);

        if (str_contains(strtoupper($message), 'OPERATION_NOT_ALLOWED')) {
            $this->line('      → Enable Google under Authentication → Sign-in method.');
        }

        if (str_contains(strtoupper($message), 'INVALID_API_KEY') || str_contains(strtoupper($message), 'API_KEY')) {
            $this->line('      → FIREBASE_API_KEY does not belong to this project.');
        }
    } elseif ($authRes !== null) {
        $authUri = (string) ($authRes->json('authUri') ?? '');
        parse_str((string) parse_url($authUri, PHP_URL_QUERY), $query);
        $clientId = (string) ($query['client_id'] ?? '');
        $redirectUri = (string) ($query['redirect_uri'] ?? '');

        $this->line(' <info>OK</info>   Google provider is enabled');
        $this->line('      OAuth client: '.($clientId !== '' ? $clientId : '(unknown)'));
        $this->line('      Redirect URI: '.($redirectUri !== '' ? $redirectUri : '(unknown)'));

        if ($clientId !== '' && $number !== '' && ! str_starts_with($clientId, $number.'-')) {
            $failed = true;
            $this->line(" <error>FAIL</error> that client belongs to another project, not {$number}.");
            $this->line('      → A custom OAuth client is configured in Firebase → Authentication → Google.');
            $this->line("      → It must allow https://{$authDomain}/__/auth/handler as a redirect URI,");
            $this->line('        or clear it so Firebase uses its own client.');
        }

        if ($authDomain !== '') {
            $this->line("      Redirect URI to allow: https://{$authDomain}/__/auth/handler");
        }

        // Google validates client_id + redirect_uri before it shows consent, so
        // a plain probe of that URL tells us whether the popup would be refused.
        if ($authUri !== '') {
            try {
                $probe = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; intercitywheels firebase:check)'])
                    ->get($authUri);

                $body = $probe->body();
                $effective = (string) $probe->effectiveUri();

                if (str_contains($body, 'redirect_uri_mismatch') || str_contains($effective, 'redirect_uri_mismatch')) {
                    $failed = true;
                    $this->line(' <error>FAIL</error> Google rejects the redirect URI (redirect_uri_mismatch).');
                    $this->line('      → Add '.($redirectUri !== '' ? $redirectUri : 'https://'.$authDomain.'/__/auth/handler'));
                    $this->line('        to that client\'s Authorized redirect URIs in Google Cloud → APIs & Services → Credentials.');
                } elseif (str_contains($body, 'invalid_client')) {
                    $failed = true;
                    $this->line(' <error>FAIL</error> Google does not recognise the OAuth client (invalid_client).');
                } elseif (str_contains($body, 'deleted_client')) {
                    $failed = true;
                    $this->line(' <error>FAIL</error> The OAuth client used by this project was deleted.');
                } elseif ($probe->status() >= 400) {
                    $this->warn('      Google answered HTTP '.$probe->status().' for the sign-in URL.');
                } else {
                    $this->line(' <info>OK</info>   Google accepts the redirect URI (consent page served)');
                }
            } catch (Throwable $e) {
                $this->warn('      Could not probe the sign-in URL: '.$e->getMessage());
            }
        }
    }

    $this->newLine();

    if ($failed) {
        $this->error('Firebase sign-in will fail until the FAIL lines above are fixed.');

        return 1;
    }

    $this->info('Firebase side looks consistent. If Google still refuses, check the OAuth consent screen (test users / publishing).');

    return 0;
})->purpose('Diagnose Firebase Auth: key project, authorized domains, Google provider and OAuth client');

Artisan::command('bidding:close-expired', function (BiddingService $bidding) {
    $closed = $bidding->closeDueSessions();
    $this->info("Closed {$closed} expired bidding session(s).");
})->purpose('Close driver auctions whose deadline has passed');

// Safety net: the request path also closes an auction lazily, so a missed cron
// run can never leave an expired auction accepting bids.
Schedule::command('bidding:close-expired')->everyMinute()->withoutOverlapping();

// Expired rows are already ignored on read, so pruning only reclaims space; it
// never changes a quote.
Schedule::command('geo:prune-cache')->daily();
