<?php

return [
    /*
    | Secret "pepper" mixed into token hashes (HMAC-SHA256). Set NWS_TOKEN_PEPPER in .env to a long random
    | value (e.g. `php -r "echo bin2hex(random_bytes(32));"`). Changing it invalidates all unredeemed tokens.
    | Falls back to a value derived from APP_KEY so a fresh install works, but set it explicitly in production.
    */
    'token_pepper' => env('NWS_TOKEN_PEPPER') ?: hash('sha256', 'nws-token-pepper|'.env('APP_KEY', '')),

    // Unambiguous alphabet (no 0/O, 1/I/L). 32 symbols => 5 bits per character.
    'token_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',

    // Lockout after repeated failed redemptions from the same device/IP.
    'redeem_lockout_seconds' => 900,

    // Base URL shown in voucher QR codes (the captive portal address on the hotspot LAN).
    'portal_url' => env('NWS_PORTAL_URL', 'http://10.10.0.1/portal'),
];
