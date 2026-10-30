<?php
/**
 * LemboEvents — private configuration TEMPLATE.
 *
 * 1. Copy this file to config/local.php
 * 2. Fill in your own values
 *
 * config/local.php is listed in .gitignore and must never be committed.
 * It is a PHP file that only returns an array, so it prints nothing if requested
 * from a browser (and config/.htaccess blocks direct access anyway).
 */
return [
    // ---- App ----
    'APP_ENV'    => 'development',   // set to 'production' on a live server
    // Long random string used to sign ticket QR codes. Generate one with:
    //   php -r "echo bin2hex(random_bytes(32));"
    'APP_SECRET' => '',
    // Leave empty for automatic detection. Example: '/LemboEvents'
    'BASE_URL'   => '',

    // ---- Database (XAMPP defaults) ----
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'lemboevents',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // ---- LemboBot (Groq) ----
    // Create a key at https://console.groq.com/keys — it stays on the server, never in JavaScript.
    'GROQ_API_KEY' => '',
    // Optional. Any chat model available to your key, e.g. 'openai/gpt-oss-120b',
    // 'openai/gpt-oss-20b' or 'llama-3.3-70b-versatile'.
    'GROQ_MODEL'   => '',
    // Optional. Path to cacert.pem if cURL reports "SSL certificate problem" on Windows/XAMPP.
    'CHATBOT_CA_BUNDLE' => '',
];
