<?php
/**
 * Seiger Multisite Tools plugin for Evolution CMS.
 *
 * Provides multisite runtime configuration and Manager UX improvements:
 * - Switches Evolution config (site_key/start pages) by the current domain.
 * - Namespaces page cache keys per site_key.
 * - Rebuilds cached multi-domain tree on cache refresh.
 * - Adjusts Manager document URLs to point to the owning domain.
 * - Enhances Manager tree with extra domain roots and special node icons.
 *
 * Also provides optional cross-domain Manager SSO (single sign-on):
 * - After Manager login: propagates the session SID to other domains.
 * - After Manager logout: clears the session SID on other domains.
 * - Uses short-lived signed tokens (HS256) and a per-run "plan" (steps).
 * - Executes in the same tab via location.replace() to avoid popup blockers.
 *
 * Endpoints (friendly_url_suffix is respected):
 * - /_ms-run, /_ms-run-logout       Runner endpoints (step sequencer)
 * - /_ms-sso, /_ms-sso-logout       Receiver endpoints (set/unset SID)
 */

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sMultisite\Facades\sMultisite;

// Load SSO functions if needed
if (!function_exists('ms_sso_load_functions')) {
    /**
     * Load SSO helper functions once.
     *
     * The helpers are expected at: ../functions/sso.php
     * - ms_sso_token_make()
     * - ms_sso_token_parse()
     * - ms_run_put(), ms_run_get(), ms_run_touch(), ms_run_del()
     *
     * @return void
     */
    function ms_sso_load_functions(): void {
        static $loaded = false;
        if (!$loaded) {
            $ssoFile = __DIR__ . '/../functions/sso.php';
            if (file_exists($ssoFile)) {
                require_once $ssoFile;
                $loaded = true;
            }
        }
    }
}

/**
 * OnLoadSettings
 *
 * Switches Evolution configuration by domain:
 * - site_key, site_root, site_name
 * - site_start, error_page, unauthorized_page
 * - site_color
 *
 * Also hydrates UrlProcessor::documentListing from cache for the selected site_key.
 *
 * @param array $params Event payload (may include ['config']['setHost'] override)
 *
 * @return void
 */
Event::listen('evolution.OnLoadSettings', function($params) {
    $host = $_SERVER['HTTP_HOST'];
    if (isset($params['config']['setHost']) && trim($params['config']['setHost']) !== '') {
        $host = trim($params['config']['setHost']);
    }
    evo()->setConfig('site_key', 'default');
    evo()->setConfig('site_root', 0);
    evo()->setConfig('site_color', '#60a5fa');
    $domain = \Seiger\sMultisite\Models\sMultisite::whereDomain($host)->whereActive(1)->first();
    if ($domain) {
        evo()->setConfig('site_key', $domain->key);
        evo()->setConfig('site_name', $domain->site_name);
        evo()->setConfig('site_start', $domain->site_start);
        evo()->setConfig('error_page', $domain->error_page);
        evo()->setConfig('unauthorized_page', $domain->unauthorized_page);
        evo()->setConfig('site_root', (int)$domain->resource);
        evo()->setConfig('site_color', $domain->site_color);
    }
    $aliasListing = Cache::get('sMultisite-' . evo()->getConfig('site_key', 'default') . '-resources') ?? [];
    if (is_array($aliasListing)) {
        UrlProcessor::getFacadeRoot()->documentListing = $aliasListing;
    }
});

/**
 * OnWebPageInit
 *
 * Enforces resource access per domain on the front-end:
 * - Compares evo()->documentIdentifier against cached allowed resource IDs for the current site_key.
 * - Whitelists SSO service endpoints to avoid accidental 404 / access blocks.
 *
 * @return void
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
 * OnMakeDocUrl
 *
 * For Manager links, prefixes document URLs with the domain that owns the resource root.
 * This allows correct cross-domain navigation from Manager UI.
 *
 * @param array $params Event payload:
 *                     - int    $params['id']  Document ID
 *                     - string $params['url'] Generated (relative) URL
 *
 * @return string|null Prefixed URL for Manager context, otherwise null to keep default behavior
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
 * OnMakePageCacheKey
 *
 * Namespaces page cache keys by site_key to prevent collisions across domains.
 *
 * @param array $params Event payload:
 *                     - string $params['hash'] Base cache key hash
 *
 * @return string
 */
Event::listen('evolution.OnMakePageCacheKey', function($params) {
    return evo()->getConfig('site_key', 'default') . '_' . $params['hash'];
});

/**
 * OnCacheUpdate
 *
 * Rebuilds the cached multi-domain tree/listings.
 *
 * @param array $params Event payload (unused)
 *
 * @return void
 */
Event::listen('evolution.OnCacheUpdate', function($params) {
    sMultisite::domainsTree();
});

/**
 * OnDocFormPrerender
 *
 * Ensures the System Settings panel loads values for the domain the document belongs to.
 * It resolves a host for the given document ID and triggers OnLoadSettings with setHost override.
 *
 * @param array $params Event payload:
 *                     - int|string $params['id'] Document ID being edited
 *
 * @return void
 */
Event::listen('evolution.OnDocFormPrerender', function($params) {
    $config = array_merge(evo()->config, ['setHost' => parse_url(url($params['id']), PHP_URL_HOST)]);
    evo()->invokeEvent('OnLoadSettings', ['config' => &$config]);
});

/**
 * OnManagerMenuPrerender
 *
 * Adds “sMultisite” entry to Tools for users with 'settings' permission.
 *
 * @param array $params Event payload:
 *                     - array $params['menu'] Current Manager menu structure
 *
 * @return string|null Serialized updated menu, or null to keep default behavior
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
 * OnManagerLogin
 *
 * After successful Manager login, builds a "run plan" to authenticate on other domains.
 * Stores runId in PHP session; the plan is executed from evolution.OnManagerWelcomeHome.
 *
 * Notes:
 * - Uses current session_id() as SID value to propagate.
 * - Tokens are short-lived and include mode/login, sid, and host.
 *
 * @return void
 */
Event::listen('evolution.OnManagerLogin', function () {
    ms_sso_load_functions();

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
 * OnManagerLogout
 *
 * Builds a "run plan" to logout on other domains.
 * Stores runId in a cookie so we can start from evolution.OnManagerWelcomeHome (session may be gone).
 *
 * Notes:
 * - Tokens are short-lived and include mode/logout and host.
 * - Cookie is intentionally not HttpOnly to allow JS-less redirections.
 *
 * @return void
 */
Event::listen('evolution.OnManagerLogout', function () {
    ms_sso_load_functions();

    $canon = function($h) {
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
 * OnManagerWelcomeHome
 *
 * Kicks off the run (login/logout) in the same tab using location.replace().
 * Passes "ret" (final Manager URL) so the flow returns automatically.
 *
 * @return void
 */
Event::listen('evolution.OnManagerWelcomeHome', function () {
    ms_sso_load_functions();

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
 * OnBeforeLoadDocumentObject
 *
 * Implements service endpoints:
 * - /_ms-run, /_ms-run-logout  Runner endpoints (sequence executor across domains)
 * - /_ms-sso, /_ms-sso-logout  Receiver endpoints (set/unset SID and bounce to next/ret)
 *
 * Security/behavior:
 * - Has <meta refresh> fallback for no-JS.
 * - Blocks speculative preloads/prerenders to avoid consuming one-shot tokens prematurely.
 *
 * @return void
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

    ms_sso_load_functions();

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
    $rootDom = getenv('MS_SESSION_ROOT_DOMAIN') ?: (function_exists('env') ? (string)env('MS_SESSION_ROOT_DOMAIN', '') : '');

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

        $err = null;
        $data = ms_sso_token_parse($code, $err);
        if (!$data || ($data['mode'] ?? '') !== 'login' || empty($data['sid'])) {
            error_log('[sMultisite SSO] RECEIVER login invalid token err=' . ($err ?? 'unknown') . ' host=' . ($_SERVER['HTTP_HOST'] ?? '') . ' sfm=' . ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
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

        $err = null;
        $data = ms_sso_token_parse($code, $err);
        if (!$data || ($data['mode'] ?? '') !== 'logout') {
            error_log('[sMultisite SSO] RECEIVER logout invalid token err=' . ($err ?? 'unknown') . ' host=' . ($_SERVER['HTTP_HOST'] ?? '') . ' sfm=' . ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? ''));
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
 * OnManagerTreePrerender
 *
 * Forces Manager tree context to the "default" domain settings.
 * This avoids Manager tree inconsistencies when switching documents/domains.
 *
 * @param array $params Event payload (unused)
 *
 * @return void
 */
Event::listen('evolution.OnManagerTreePrerender', function($params) {
    $domain = \Seiger\sMultisite\Models\sMultisite::where('key', 'default')->first();
    if ($domain) {
        evo()->setConfig('site_key', $domain->key);
        evo()->setConfig('site_name', $domain->site_name);
        evo()->setConfig('site_start', $domain->site_start);
        evo()->setConfig('error_page', $domain->error_page);
        evo()->setConfig('unauthorized_page', $domain->unauthorized_page);
        evo()->setConfig('site_root', (int)$domain->resource);
        evo()->setConfig('site_color', $domain->site_color);
    }
});

/**
 * OnManagerTreeRender
 *
 * Renders extra root nodes for each domain in the Resources tree (except default).
 * Each domain is rendered as a "rootNode" container with its own treeRoot placeholder.
 *
 * @param array $params Event payload (unused)
 *
 * @return string|null HTML markup for extra roots, or null to keep default behavior
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
 * OnManagerNodePrerender
 *
 * Sets special icons and disables move for domain key pages:
 * - home (site_start)
 * - error_page
 * - unauthorized_page
 *
 * Also optionally marks sCommerce catalog root categories if enabled (check_sCommerce).
 *
 * @param array $params Event payload:
 *                     - array $params['ph'] Placeholder array for a node (must be returned serialized)
 *
 * @return string|null Serialized placeholders, or null to keep default behavior
 */
Event::listen('evolution.OnManagerNodePrerender', function($params) {
    $domains = \Seiger\sMultisite\Models\sMultisite::where('hide_from_tree', 0)->get();
    if ($domains) {
        $_style = ManagerTheme::getStyle();
        $startResources = $domains->pluck('site_start')->toArray();
        $errorResources = $domains->pluck('error_page')->toArray();
        $unauthorizedResources = $domains->pluck('unauthorized_page')->toArray();

        if (evo()->getConfig('check_sCommerce', false)) {
            $rootCategoryResources =  [];
            foreach (sCommerce::config('basic', []) as $name => $id) {
                if (str_starts_with($name, 'catalog_root')) {
                    $rootCategoryResources[] = $id;
                }
            }
        }

        switch (true) {
            case in_array($params['ph']['id'], $startResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_home'] . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . $_style['icon_home'] . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . $_style['icon_home'] . "'></i>";
                break;
            case in_array($params['ph']['id'], $errorResources) :
                $params['ph']['icon'] = '<i class="' . $_style['icon_info_triangle'] . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . $_style['icon_info_triangle'] . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . $_style['icon_info_triangle'] . "'></i>";
                break;
            case in_array($params['ph']['id'], $unauthorizedResources) :
                $params['ph']['icon'] = '<i class="' . $_style['unauthorized_page'] . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . $_style['unauthorized_page'] . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . $_style['unauthorized_page'] . "'></i>";
                break;
            case in_array($params['ph']['id'], $rootCategoryResources) :
                $params['ph']['icon'] = '<i class="' . __('sCommerce::global.icon') . '"></i>';
                $params['ph']['icon_folder_open'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                $params['ph']['icon_folder_close'] = "<i class='" . __('sCommerce::global.icon') . "'></i>";
                break;
        }
        return serialize($params['ph']);
    }
});

/**
 * OnManagerNodeRender
 *
 * Hides domain container root resources from the tree.
 * If the current node ID is one of sMultisite domain container resources, returns a blank string.
 *
 * @param array $params Event payload:
 *                     - int $params['id'] Node document ID
 *
 * @return string|null Blank string to hide, or null to keep default behavior
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
