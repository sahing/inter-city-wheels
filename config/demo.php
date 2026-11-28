<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | Controls every demonstration-only surface: one-tap role login, the
    | simulated Firebase/Clerk providers, and echoing a generated OTP back to
    | the caller. All of these allow assuming an account without credentials,
    | so they must be OFF in production.
    |
    | Default: enabled everywhere except production.
    |
    */

    'enabled' => (bool) env('INTERCITY_DEMO_MODE', env('APP_ENV') !== 'production'),

];
