<?php
/**
 * Plugin for Seiger Multisite Tools to Evolution CMS.
 *
 * SSO across multiple domains living in the same Evolution CMS instance:
 * - After Manager login: propagates the session to other domains.
 * - After Manager logout: clears the session on other domains.
 * - Uses short-lived signed tokens (HS256) and a per-login "run plan" (steps).
 * - Runs in the same tab via location.replace() to avoid popup blockers.
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seiger\sMultisite\Facades\sMultisite;

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
 * OnLoadSettings:
 * Switch Evolution config by domain (site_key, start pages), and hydrate documentListing from cache.
 */
Event::listen('evolution.OnLoadSettings', function($params) {
    $host = $_SERVER['HTTP_HOST'];
    if (isset($params['config']['setHost']) && trim($params['config']['setHost']) !== '') {
        $host = trim($params['config']['setHost']);
    }
    evo()->setConfig('site_key', 'default');
    evo()->setConfig('site_root', 0);
    $domain = \Seiger\sMultisite\Models\sMultisite::whereDomain($host)->whereActive(1)->first();
    if ($domain) {
        evo()->setConfig('site_key', $domain->key);
        evo()->setConfig('site_name', $domain->site_name);
        evo()->setConfig('site_start', $domain->site_start);
        evo()->setConfig('error_page', $domain->error_page);
        evo()->setConfig('unauthorized_page', $domain->unauthorized_page);
        evo()->setConfig('site_root', (int)$domain->resource);
    }
    $aliasListing = Cache::get('sMultisite-' . evo()->getConfig('site_key', 'default') . '-resources') ?? [];
    if (is_array($aliasListing)) {
        evo()->documentListing = $aliasListing;
    }
});

/**
 * OnWebPageInit:
 * Enforce resource access per domain on the front-end.
 * Whitelist SSO endpoints to avoid accidental 404.
 */
Event::listen('evolution.OnWebPageInit', function () {
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $suffix = (string) evo()->getConfig('friendly_url_suffix', '');
    $path = $uri;
    if ($suffix && str_ends_with($path, $suffix)) $path = substr($path, 0, -strlen($suffix));
    if (in_array($path, ['/_ms-run', '/_ms-run-logout', '/_ms-sso', '/_ms-sso-logout'], true)) return;

    $domainIds = Cache::get('sMultisite-' . evo()->getConfig('site_key', 'default') . '-resources') ?? [];
    if (!in_array(evo()->documentIdentifier, $domainIds)) {
        evo()->sendErrorPage();
    }
});

/**
 * OnMakeDocUrl:
 * For Manager links, prefix URLs with the appropriate domain.
 */
Event::listen('evolution.OnMakeDocUrl', function($params) {
    if (evo()->isBackend()) {
        $roots = evo()->getParentIds($params['id']);
        if (count($roots)) {
            $root = array_pop($roots);

            $domainUrl = Cache::rememberForever('sMultisite-' . $root . '-url', function () use ($root) {
                $url = '';
                $domain = \Seiger\sMultisite\Models\sMultisite::whereResource($root)->first();
                if ($domain) {
                    $url = evo()->getConfig('server_protocol', 'https') . '://' . $domain->domain;
                }
                return $url;
            });

            return $domainUrl . $params['url'];
        }
    }
});

/**
 * OnMakePageCacheKey:
 * Namespaces page cache by site_key.
 */
Event::listen('evolution.OnMakePageCacheKey', function($params) {
    return evo()->getConfig('site_key', 'default') . '_' . $params['hash'];
});

/**
 * OnCacheUpdate:
 * Rebuild cached multi-domain tree.
 */
Event::listen('evolution.OnCacheUpdate', function($params) {
    sMultisite::domainsTree();
});

/**
 * OnDocFormPrerender:
 * Ensure System Settings panel loads values from the domain the document belongs to.
 */
Event::listen('evolution.OnDocFormPrerender', function($params) {
    $config = array_merge(evo()->config, ['setHost' => parse_url(url($params['id']), PHP_URL_HOST)]);
    evo()->invokeEvent('OnLoadSettings', ['config' => &$config]);
});

/**
 * OnManagerMenuPrerender:
 * Adds “sMultisite” entry to Tools (for users with 'settings' permission).
 */
Event::listen('evolution.OnManagerMenuPrerender', function($params) {
    if (evo()->hasPermission('settings')) {
        $menu['smultisite'] = [
            'smultisite',
            'tools',
            '<i class="' . __('sMultisite::global.icon') . '"></i>' . __('sMultisite::global.title'),
            sMultisite::route('sMultisite.configure'),
            __('sMultisite::global.title'),
            "",
            "",
            "main",
            0,
            8,
        ];

        return serialize(array_merge($params['menu'], $menu));
    }
});

/**
 * OnManagerLogin:
 * After successful Manager login, builds a "run plan" to authenticate on other domains.
 * Stores runId in session; the plan is executed from OnManagerWelcomeHome.
 */
Event::listen('evolution.OnManagerLogin', function () {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (session_status() !== PHP_SESSION_ACTIVE) return;

    $sid = session_id(); if (!$sid) return;
    $home = strtolower(parse_url((($_SERVER['HTTPS']??'')==='on'?'https':'http').'://'.($_SERVER['HTTP_HOST']??''), PHP_URL_HOST));

    // Normalize host strings.
    $canon = function($h){
        $h = trim($h);
        $h = preg_replace('~^https?://~i','',$h);
        $h = rtrim($h, '/');
        return strtolower($h);
    };

    $targets = \Seiger\sMultisite\Models\sMultisite::query()
        ->pluck('domain')
        ->filter()
        ->map($canon)
        ->unique()
        ->reject(fn($h)=>$h === $canon($home) || $h === '')
        ->values()
        ->all();

    error_log('[sMultisite SSO] LOGIN targets=' . json_encode($targets, JSON_UNESCAPED_SLASHES));

    if (!$targets) return;

    $steps = [];
    foreach ($targets as $host) {
        $token = ms_sso_token_make(['mode' => 'login', 'sid' => $sid, 'host' => $host], 180);
        $steps[] = ['host' => $host, 'code' => $token];
    }

    $runId = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    ms_run_put($runId, ['home' => $canon($home), 'steps' => $steps], 300);

    error_log('[sMultisite SSO] LOGIN runId=' . $runId . ' steps_count=' . count($steps) . ' home=' . $canon($home));
    $_SESSION['ms_run_login'] = $runId;
});

/**
 * OnManagerLogout:
 * Builds a "run plan" to logout on other domains.
 * Stores runId in a cookie so we can start from OnManagerWelcomeHome.
 */
Event::listen('evolution.OnManagerLogout', function () {
    $canon = function($h){
        $h = trim($h);
        $h = preg_replace('~^https?://~i', '', $h);
        $h = rtrim($h, '/');
        return strtolower($h);
    };
    $home = $canon(parse_url((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));

    $targets = \Seiger\sMultisite\Models\sMultisite::query()
        ->pluck('domain')->filter()->map($canon)->unique()
        ->reject(fn($h) => $h === $home || $h === '')->values()->all();

    error_log('[sMultisite SSO] LOGOUT targets=' . json_encode($targets, JSON_UNESCAPED_SLASHES));

    if (!$targets) return;

    $steps = [];
    foreach ($targets as $host) {
        $token = ms_sso_token_make(['mode' => 'logout', 'host' => $host], 180);
        $steps[] = ['host' => $host, 'code' => $token];
    }
    $runId = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    ms_run_put($runId, ['home' => $home, 'steps' => $steps], 300);

    error_log('[sMultisite SSO] LOGOUT runId=' . $runId . ' steps_count=' . count($steps) . ' home=' . $home);

    $secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])==='on') || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    setcookie('ms_run_logout', $runId, [
        'expires' => time()+180, 'path' => '/', 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax',
    ]);
});

/**
 * OnManagerWelcomeHome:
 * Kicks off the run (login/logout) in the same tab using location.replace().
 * We pass "ret" (final Manager URL) so the flow comes back automatically.
 */
Event::listen('evolution.OnManagerWelcomeHome', function () {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

    $loginRun  = $_SESSION['ms_run_login']  ?? null;
    $logoutRun = $_COOKIE['ms_run_logout'] ?? null;
    if (!$loginRun && !$logoutRun) return;

    $suffix = (string) evo()->getConfig('friendly_url_suffix', '');
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on')
        || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $isHttps ? 'https' : 'http';

    // Where to return after SSO propagation completes
    $finalReturn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/manager/?a=2');

    $startLoginUrl = '';
    if ($loginRun) {
        $run = ms_run_get($loginRun);
        if (is_array($run) && !empty($run['home']) && !empty($run['steps'][0])) {
            $first  = $run['steps'][0];
            $return = $scheme . '://' . $run['home'] . '/_ms-run' . $suffix . '?run=' . $loginRun . '&i=1&ret=' . rawurlencode($finalReturn);
            $startLoginUrl = 'https://' . $first['host'] . '/_ms-sso' . $suffix . '?c=' . rawurlencode($first['code']) . '&return=' . rawurlencode($return);
        }
    }

    $startLogoutUrl = '';
    if ($logoutRun) {
        $run = ms_run_get($logoutRun);
        if (is_array($run) && !empty($run['home']) && !empty($run['steps'][0])) {
            $first  = $run['steps'][0];
            $return = $scheme . '://' . $run['home'] . '/_ms-run-logout' . $suffix . '?run=' . $logoutRun . '&i=1&ret=' . rawurlencode($finalReturn);
            $startLogoutUrl = 'https://' . $first['host'] . '/_ms-sso-logout' . $suffix . '?c=' . rawurlencode($first['code']) . '&return=' . rawurlencode($return);
        }
    }

    // one-time use
    unset($_SESSION['ms_run_login']);
    if ($logoutRun) setcookie('ms_run_logout', '', time() - 3600, '/');

    $start = $startLoginUrl ?: $startLogoutUrl;
    if (!$start) return;

    $jsStart = json_encode($start, JSON_UNESCAPED_SLASHES);
    echo <<<HTML
<div style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:99999;display:flex;align-items:center;justify-content:center;font:14px/1.4 system-ui,Segoe UI,Arial;color:#fff">
  <div style="background:#111;padding:14px 18px;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.35)">Synchronizing the session on other domains…</div>
</div>
<script>location.replace($jsStart);</script>
HTML;
});

/**
 * OnBeforeLoadDocumentObject:
 * Implements service endpoints:
 *  - /_ms-run, /_ms-run-logout  → runners (sequence executor across domains)
 *  - /_ms-sso, /_ms-sso-logout  → receivers (set/unset SID and bounce to next/ret)
 * Has <meta refresh> fallback and blocks preloads to avoid consuming tokens prematurely.
 */
Event::listen('evolution.OnBeforeLoadDocumentObject', function () {
    $uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $suffix = (string) evo()->getConfig('friendly_url_suffix', '');
    $path   = $uri;
    if ($suffix) {
        $ends = function_exists('str_ends_with')
            ? str_ends_with($path, $suffix)
            : substr($path, -strlen($suffix)) === $suffix;
        if ($ends) $path = substr($path, 0, -strlen($suffix));
    }

    if (!in_array($path, ['/_ms-run', '/_ms-run-logout', '/_ms-sso', '/_ms-sso-logout'], true)) return;

    // Prevent speculative preloads/prerenders from consuming one-shot tokens
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $sfm    = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
    if ($method !== 'GET' || ($sfm && strtolower($sfm) !== 'navigate')) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        return;
    }

    $https  = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on')
        || (($_SERVER['SERVER_PORT'] ?? 0) == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $slow   = isset($_GET['slow']);
    $cookie = defined('SESSION_COOKIE_NAME') ? SESSION_COOKIE_NAME : session_name();
    $rootDom = getenv('MS_SESSION_ROOT_DOMAIN') ?: '';

    $noStore = static function () {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    };

    // ---------------- RUNNER: LOGIN ----------------
    if ($path === '/_ms-run') {
        $runId = (string)($_GET['run'] ?? '');
        $i     = max(0, (int)($_GET['i'] ?? 0));
        $ret   = (string)($_GET['ret'] ?? ''); // final return URL for Manager
        $run   = $runId ? ms_run_get($runId) : null;

        $noStore();
        header('Content-Type: text/html; charset=UTF-8');

        if (!$run || empty($run['steps']) || empty($run['home'])) {
            echo "<h2>SMultisite SSO: Plan not found</h2>";
            exit;
        }

        ms_run_touch($runId, 300);
        $steps = $run['steps'];
        $home  = $run['home'];

        // All steps done → return to Manager or show "done"
        if ($i >= count($steps)) {
            ms_run_del($runId);
            if ($ret !== '') {
                $jsRet = json_encode($ret, JSON_UNESCAPED_SLASHES);
                echo "<script>location.replace($jsRet);</script>";
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($ret, ENT_QUOTES) . '"></noscript>';
            } else {
                echo "<h2>SMultisite SSO: Done ✔</h2><script>setTimeout(function(){window.close&&window.close();},800);</script>";
            }
            exit;
        }

        $step = $steps[$i];
        $next = "{$scheme}://{$home}/_ms-run{$suffix}?run=" . rawurlencode($runId) . "&i=" . ($i + 1)
            . ($ret !== '' ? '&ret=' . rawurlencode($ret) : '')
            . ($slow ? '&slow=1' : '');
        $url  = "https://{$step['host']}/_ms-sso{$suffix}?c=" . rawurlencode($step['code'])
            . "&return=" . rawurlencode($next) . ($slow ? '&slow=1' : '');

        $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        if ($slow) {
            echo "<h2>Step " . ($i + 1) . "/" . count($steps) . " → " . htmlspecialchars($step['host']) . "</h2>";
            echo "<p><code>" . htmlspecialchars($url, ENT_QUOTES) . "</code></p>";
            echo "<script>setTimeout(function(){location.replace($jsUrl);}, 800);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        } else {
            echo "<script>location.replace($jsUrl);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        }
        exit;
    }

    // ---------------- RUNNER: LOGOUT ----------------
    if ($path === '/_ms-run-logout') {
        $runId = (string)($_GET['run'] ?? '');
        $i     = max(0, (int)($_GET['i'] ?? 0));
        $ret   = (string)($_GET['ret'] ?? '');
        $run   = $runId ? ms_run_get($runId) : null;

        $noStore();
        header('Content-Type: text/html; charset=UTF-8');

        if (!$run || empty($run['steps']) || empty($run['home'])) {
            echo "<h2>SMultisite SSO logout: Plan not found</h2>";
            exit;
        }

        ms_run_touch($runId, 300);
        $steps = $run['steps'];
        $home  = $run['home'];

        if ($i >= count($steps)) {
            ms_run_del($runId);
            if ($ret !== '') {
                $jsRet = json_encode($ret, JSON_UNESCAPED_SLASHES);
                echo "<script>location.replace($jsRet);</script>";
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($ret, ENT_QUOTES) . '"></noscript>';
            } else {
                echo "<h2>SMultisite SSO logout: Done ✔</h2><script>setTimeout(function(){window.close&&window.close();},800);</script>";
            }
            exit;
        }

        $step = $steps[$i];
        $next = "{$scheme}://{$home}/_ms-run-logout{$suffix}?run=" . rawurlencode($runId) . "&i=" . ($i + 1)
            . ($ret !== '' ? '&ret=' . rawurlencode($ret) : '')
            . ($slow ? '&slow=1' : '');
        $url  = "https://{$step['host']}/_ms-sso-logout{$suffix}?c=" . rawurlencode($step['code'])
            . "&return=" . rawurlencode($next) . ($slow ? '&slow=1' : '');

        $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        if ($slow) {
            echo "<h2>Logout " . ($i + 1) . "/" . count($steps) . " → " . htmlspecialchars($step['host']) . "</h2>";
            echo "<p><code>" . htmlspecialchars($url, ENT_QUOTES) . "</code></p>";
            echo "<script>setTimeout(function(){location.replace($jsUrl);}, 800);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        } else {
            echo "<script>location.replace($jsUrl);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        }
        exit;
    }

    // ---------------- RECEIVER: LOGIN ----------------
    if ($path === '/_ms-sso') {
        $code   = (string)($_GET['c'] ?? '');
        $return = (string)($_GET['return'] ?? '/');

        $data = ms_sso_token_parse($code);
        if (!$data || ($data['mode'] ?? '') !== 'login' || empty($data['sid'])) {
            header('HTTP/1.1 400 Bad Request'); echo 'Invalid/expired'; exit;
        }

        $noStore();
        setcookie($cookie, $data['sid'], [
            'expires'  => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($slow) {
            header('Content-Type: text/html; charset=UTF-8');
            echo "<h3>SID set on " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . "</h3>";
            $jsReturn = json_encode($return, JSON_UNESCAPED_SLASHES);
            echo "<script>setTimeout(function(){location.replace($jsReturn);}, 500);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($return, ENT_QUOTES) . '"></noscript>';
            exit;
        }
        header('Location: ' . $return, true, 303); exit;
    }

    // ---------------- RECEIVER: LOGOUT ----------------
    if ($path === '/_ms-sso-logout') {
        $code   = (string)($_GET['c'] ?? '');
        $return = (string)($_GET['return'] ?? '/');

        $data = ms_sso_token_parse($code);
        if (!$data || ($data['mode'] ?? '') !== 'logout') {
            header('HTTP/1.1 400 Bad Request'); echo 'Invalid/expired'; exit;
        }

        $noStore();
        setcookie($cookie, '', time()-3600, '/', '', $https, true);
        if ($rootDom) setcookie($cookie, '', time()-3600, '/', $rootDom, $https, true);

        if ($slow) {
            header('Content-Type: text/html; charset=UTF-8');
            echo "<h3>SID cleared on " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? '') . "</h3>";
            $jsReturn = json_encode($return, JSON_UNESCAPED_SLASHES);
            echo "<script>setTimeout(function(){location.replace($jsReturn);}, 500);</script>";
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($return, ENT_QUOTES) . '"></noscript>';
            exit;
        }
        header('Location: ' . $return, true, 303); exit;
    }
});

/**
 * OnManagerTreeRender:
 * Renders extra root nodes for each domain in the Resources tree.
 */
Event::listen('evolution.OnManagerTreeRender', function($params) {
    $tree = '';
    $_style = ManagerTheme::getStyle();
    $domains = \Seiger\sMultisite\Models\sMultisite::where('hide_from_tree', 0)->whereNot('key', 'default')->get();
    if ($domains) {
        foreach ($domains as $domain) {
            $tree .= '<div id="node'.$domain->resource.'" class="rootNode" style="position:initial;">';
            $tree .= '<a class="node" onclick="modx.tree.treeAction(event, '.$domain->resource.')" data-id="'.$domain->resource.'" data-title-esc="'.$domain->site_name.'">';
            $tree .= '<span class="icon"><i class="'.$_style['icon_sitemap'].'"></i></span>';
            if ($domain->active) {
                $tree .= '<span class="title">';
            } else {
                $tree .= '<span class="title" style="color:#d0726b;">';
            }
            $tree .= $domain->site_name.'</span></a></div>';
            $tree .= '<div id="treeRoot'.$domain->resource.'" class="treeRoot"></div>';
        }
        return $tree;
    }
});

/**
 * OnManagerNodePrerender:
 * Sets special icons and disables move for domain's key pages (home/error/unauthorized).
 */
Event::listen('evolution.OnManagerNodePrerender', function($params) {
    $domains = \Seiger\sMultisite\Models\sMultisite::where('hide_from_tree', 0)->whereNot('key', 'default')->get();
    if ($domains) {
        $_style = ManagerTheme::getStyle();
        $startResources = $domains->pluck('site_start')->toArray();
        $errorResources = $domains->pluck('error_page')->toArray();
        $unauthorizedResources = $domains->pluck('unauthorized_page')->toArray();
        switch (true) {
            case in_array($params['ph']['id'], $startResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_home'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
            case in_array($params['ph']['id'], $errorResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_info_triangle'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
            case in_array($params['ph']['id'], $unauthorizedResources) :
                $params['ph']['icon'] = '<i class="' . $_style['unauthorized_page'] . '"></i>';
                $params['ph']['nomove'] = '1';
                break;
        }
        return serialize($params['ph']);
    }
});

/**
 * OnManagerNodeRender:
 * Hides root resources that act as domain containers from the tree.
 */
Event::listen('evolution.OnManagerNodeRender', function($params) {
    $domains = \Seiger\sMultisite\Models\sMultisite::all();
    if ($domains) {
        $multisiteResources = $domains->pluck('resource')->toArray();
        if (is_array($multisiteResources) && in_array($params['id'], $multisiteResources)) {
            return ' ';
        }
    }
});
