<?php
/**
 * SSO Functions for sMultisite
 * 
 * This file contains SSO-related functions that are loaded only when needed.
 * Functions are defined here to avoid redefining them on every plugin call.
 */

/**
 * Base64url-safe encode.
 *
 * @param string $s Raw bytes
 * @return string Base64url string without '='
 */
if (!function_exists('ms_b64u')) {
    function ms_b64u(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
}

/**
 * JSON → base64url-safe encode.
 *
 * @param array $a Data to encode as JSON
 * @return string Base64url-encoded JSON
 */
if (!function_exists('ms_b64u_json')) {
    function ms_b64u_json(array $a): string { return ms_b64u(json_encode($a, JSON_UNESCAPED_SLASHES)); }
}

/**
 * Obtain a shared secret used for signing tokens (HS256).
 *
 * Order of precedence:
 *  1) SMULTI_SSO_SECRET from environment (recommended: same across domains).
 *  2) If absent — read/write core/storage/ms_sso/secret.key (shared FS).
 *  3) Derive the final key by HMAC with SESSION_COOKIE_NAME as "info"/salt.
 *
 * @return string Raw bytes (string) to be used as HMAC key.
 */
if (!function_exists('ms_sso_secret')) {
    function ms_sso_secret(): string {
        static $sec;
        if ($sec) return $sec;

        $base = getenv('SMULTI_SSO_SECRET');
        if (!$base || strlen($base) < 32) {
            $dir  = rtrim(EVO_CORE_PATH ?? __DIR__, '/') . '/storage/ms_sso';
            $file = $dir . '/secret.key';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            if (is_file($file)) {
                $base = trim((string)@file_get_contents($file));
            } else {
                $base = bin2hex(random_bytes(32));
                @file_put_contents($file, $base, LOCK_EX);
            }
        }

        $cookieName = defined('SESSION_COOKIE_NAME') ? SESSION_COOKIE_NAME : session_name();
        // HKDF-like derivation: bind to cookie name (makes cross-install reuse safer).
        $derived = hash_hmac('sha256', $cookieName, $base, true);
        return $sec = $derived;
    }
}

/**
 * Create a signed token (JWT-like, HS256).
 *
 * @param array $claims Custom fields (e.g., mode, sid, host)
 * @param int   $ttl    Token TTL (seconds)
 * @return string JWT string "header.payload.signature"
 */
if (!function_exists('ms_sso_token_make')) {
    function ms_sso_token_make(array $claims, int $ttl = 180): string {
        $now = time();
        $payload = $claims + ['iat' => $now, 'nbf' => $now - 5, 'exp' => $now + $ttl];
        $head = ['alg' => 'HS256', 'typ' => 'JWT'];
        $seg = ms_b64u_json($head) . '.' . ms_b64u_json($payload);
        $sig = ms_b64u(hash_hmac('sha256', $seg, ms_sso_secret(), true));
        return $seg . '.' . $sig;
    }
}

/**
 * Parse and validate token.
 *
 * @param string $jwt Incoming JWT
 * @return array|null Payload if valid; null otherwise
 */
if (!function_exists('ms_sso_token_parse')) {
    function ms_sso_token_parse(string $jwt): ?array {
        if (!preg_match('~^[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$~', $jwt)) return null;
        [$h, $p, $s] = explode('.', $jwt, 3);
        $calc = ms_b64u(hash_hmac('sha256', "$h.$p", ms_sso_secret(), true));
        if (!hash_equals($calc, $s)) return null;
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!$payload || !is_array($payload)) return null;
        $now = time();
        if (isset($payload['nbf']) && $payload['nbf'] > $now) return null;
        if (isset($payload['exp']) && $payload['exp'] < $now) return null;
        return $payload;
    }
}

/**
 * Directory for SSO "run plans".
 *
 * @return string Absolute path
 */
if (!function_exists('ms_run_store_dir')) {
    function ms_run_store_dir(): string {
        $base = rtrim(EVO_CORE_PATH ?? __DIR__, '/') . '/storage/ms_sso/runs';
        if (!is_dir($base)) @mkdir($base, 0775, true);
        return $base;
    }
}

/**
 * Persist run plan with TTL.
 *
 * Structure: ['home' => host, 'steps' => [['host' => ..., 'code' => ...], ... ]]
 *
 * @param string $id      Run identifier
 * @param array  $payload Run payload
 * @param int    $ttl     Lifetime in seconds
 * @return void
 */
if (!function_exists('ms_run_put')) {
    function ms_run_put(string $id, array $payload, int $ttl = 300): void {
        $path = ms_run_store_dir() . '/' . preg_replace('~[^A-Za-z0-9_\-]~', '', $id) . '.json';
        $data = ['exp' => time() + $ttl, 'data' => $payload];
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

/**
 * Get run plan or null if missing/expired.
 *
 * @param string $id Run identifier
 * @return array|null
 */
if (!function_exists('ms_run_get')) {
    function ms_run_get(string $id): ?array {
        $path = ms_run_store_dir() . '/' . preg_replace('~[^A-Za-z0-9_\-]~', '', $id) . '.json';
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        $obj = $raw ? json_decode($raw, true) : null;
        if (!$obj || empty($obj['exp']) || $obj['exp'] < time()) { @unlink($path); return null; }
        return $obj['data'] ?? null;
    }
}

/**
 * Touch/extend TTL for existing run.
 *
 * @param string $id  Run identifier
 * @param int    $ttl New TTL
 * @return void
 */
if (!function_exists('ms_run_touch')) {
    function ms_run_touch(string $id, int $ttl = 300): void {
        $path = ms_run_store_dir() . '/' . preg_replace('~[^A-Za-z0-9_\-]~', '', $id) . '.json';
        if (!is_file($path)) return;
        $raw = @file_get_contents($path);
        $obj = $raw ? json_decode($raw, true) : null;
        if (!$obj) return;
        $obj['exp'] = time() + $ttl;
        file_put_contents($path, json_encode($obj, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

/**
 * Delete run plan after completion.
 *
 * @param string $id Run identifier
 * @return void
 */
if (!function_exists('ms_run_del')) {
    function ms_run_del(string $id): void {
        $path = ms_run_store_dir() . '/' . preg_replace('~[^A-Za-z0-9_\-]~', '', $id) . '.json';
        @unlink($path);
    }
}

/**
 * Load SSO functions if not already loaded
 */
if (!function_exists('ms_sso_load_functions')) {
    function ms_sso_load_functions(): void {
        static $loaded = false;
        if (!$loaded) {
            $ssoFile = __DIR__ . '/sso.php';
            if (file_exists($ssoFile)) {
                require_once $ssoFile;
                $loaded = true;
            }
        }
    }
}
