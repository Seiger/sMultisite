<?php namespace Seiger\sMultisite\Controllers;

use Illuminate\Http\Request;
use Seiger\sMultisite\Models\sMultisite;

class SsoController
{
    public function handle(Request $request, string $endpoint = '')
    {
        $this->loadFunctions();

        if ($endpoint === '') {
            $endpoint = (string) $request->route('ssoEndpoint', '');
        }

        if (!in_array($endpoint, ['_ms-run', '_ms-run-logout', '_ms-sso', '_ms-sso-logout'], true)) {
            abort(404);
        }

        $method = strtoupper($request->server('REQUEST_METHOD', 'GET'));
        $fetchMode = strtolower((string) $request->server('HTTP_SEC_FETCH_MODE', ''));
        if ($method !== 'GET' || ($fetchMode !== '' && $fetchMode !== 'navigate')) {
            return response('', 204)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache');
        }

        if ($endpoint === '_ms-run') {
            return $this->runLogin($request);
        }
        if ($endpoint === '_ms-run-logout') {
            return $this->runLogout($request);
        }
        if ($endpoint === '_ms-sso') {
            return $this->receiveLogin($request);
        }

        return $this->receiveLogout($request);
    }

    protected function runLogin(Request $request)
    {
        $runId = (string) $request->query('run', '');
        $i = max(0, (int) $request->query('i', 0));
        $ret = $this->safeReturn((string) $request->query('ret', ''));
        $run = $runId ? ms_run_get($runId) : null;

        if (!$run || empty($run['steps']) || empty($run['home'])) {
            return $this->html('<h2>SMultisite SSO: Plan not found</h2>');
        }

        ms_run_touch($runId, 300);

        return $this->nextStep($request, $runId, $run, $i, $ret, false);
    }

    protected function runLogout(Request $request)
    {
        $runId = (string) $request->query('run', '');
        $i = max(0, (int) $request->query('i', 0));
        $ret = $this->safeReturn((string) $request->query('ret', ''));
        $run = $runId ? ms_run_get($runId) : null;

        if (!$run || empty($run['steps']) || empty($run['home'])) {
            return $this->html('<h2>SMultisite SSO logout: Plan not found</h2>');
        }

        ms_run_touch($runId, 300);

        return $this->nextStep($request, $runId, $run, $i, $ret, true);
    }

    protected function receiveLogin(Request $request)
    {
        $err = null;
        $data = ms_sso_token_parse((string) $request->query('c', ''), $err);

        if (!$this->validToken($data, 'login') || empty($data['sid'])) {
            error_log('[sMultisite SSO] RECEIVER login invalid token err=' . ($err ?? 'unknown') . ' host=' . $this->currentHost());
            return response('Invalid/expired', 400)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $this->setSidCookies((string) $data['sid']);
        $return = $this->safeReturn((string) $request->query('return', '/'));

        return redirect()->away($return, 303)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    protected function receiveLogout(Request $request)
    {
        $err = null;
        $data = ms_sso_token_parse((string) $request->query('c', ''), $err);

        if (!$this->validToken($data, 'logout')) {
            error_log('[sMultisite SSO] RECEIVER logout invalid token err=' . ($err ?? 'unknown') . ' host=' . $this->currentHost());
            return response('Invalid/expired', 400)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $this->clearSidCookies();
        $return = $this->safeReturn((string) $request->query('return', '/'));

        return redirect()->away($return, 303)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    protected function nextStep(Request $request, string $runId, array $run, int $i, string $ret, bool $logout)
    {
        $steps = $run['steps'];
        $home = $this->canon((string) $run['home']);

        if ($i >= count($steps)) {
            ms_run_del($runId);
            $target = $ret !== '' ? $ret : '/manager/';
            return $this->redirectHtml($target);
        }

        $step = $steps[$i];
        $host = $this->canon((string) ($step['host'] ?? ''));
        $code = (string) ($step['code'] ?? '');
        if ($host === '' || $code === '') {
            return $this->html('<h2>SMultisite SSO: Invalid plan step</h2>', 400);
        }

        $suffix = (string) evo()->getConfig('friendly_url_suffix', '');
        $scheme = $this->isHttps() ? 'https' : 'http';
        $runEndpoint = $logout ? '_ms-run-logout' : '_ms-run';
        $ssoEndpoint = $logout ? '_ms-sso-logout' : '_ms-sso';

        $next = "{$scheme}://{$home}/{$runEndpoint}{$suffix}?run=" . rawurlencode($runId) . '&i=' . ($i + 1)
            . ($ret !== '' ? '&ret=' . rawurlencode($ret) : '');
        $url = "https://{$host}/{$ssoEndpoint}{$suffix}?c=" . rawurlencode($code) . '&return=' . rawurlencode($next);

        return $this->redirectHtml($url);
    }

    protected function validToken(?array $data, string $mode): bool
    {
        if (!$data || ($data['mode'] ?? '') !== $mode) {
            return false;
        }

        $host = $this->canon((string) ($data['host'] ?? ''));
        return $host !== '' && $host === $this->currentHost();
    }

    protected function safeReturn(string $return): string
    {
        if ($return === '') {
            return '';
        }

        $host = $this->canon((string) parse_url($return, PHP_URL_HOST));
        if ($host === '') {
            return strpos($return, '/') === 0 ? $return : '/';
        }

        return in_array($host, $this->allowedHosts(), true) ? $return : '/';
    }

    protected function allowedHosts(): array
    {
        static $hosts;
        if (is_array($hosts)) {
            return $hosts;
        }

        $hosts = sMultisite::query()
            ->pluck('domain')
            ->filter()
            ->map(fn($host) => $this->canon((string) $host))
            ->unique()
            ->values()
            ->all();

        $hosts[] = $this->currentHost();

        return array_values(array_unique(array_filter($hosts)));
    }

    protected function sessionCookieNames(): array
    {
        $names = [];

        if (defined('EVO_SESSION') && EVO_SESSION && function_exists('config')) {
            $names[] = (string) config('session.cookie', 'evo_session');
        }

        if (defined('SESSION_COOKIE_NAME') && SESSION_COOKIE_NAME !== '') {
            $names[] = SESSION_COOKIE_NAME;
        }

        $names[] = session_name();

        return array_values(array_unique(array_filter($names)));
    }

    protected function setSidCookies(string $sid): void
    {
        foreach ($this->sessionCookieNames() as $cookie) {
            setcookie($cookie, $sid, [
                'expires' => 0,
                'path' => '/',
                'secure' => $this->isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    protected function clearSidCookies(): void
    {
        $rootDom = getenv('MS_SESSION_ROOT_DOMAIN') ?: (function_exists('env') ? (string) env('MS_SESSION_ROOT_DOMAIN', '') : '');

        foreach ($this->sessionCookieNames() as $cookie) {
            setcookie($cookie, '', time() - 3600, '/', '', $this->isHttps(), true);
            if ($rootDom !== '') {
                setcookie($cookie, '', time() - 3600, '/', $rootDom, $this->isHttps(), true);
            }
        }
    }

    protected function redirectHtml(string $url)
    {
        $jsUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        $html = "<script>location.replace({$jsUrl});</script>"
            . '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';

        return $this->html($html);
    }

    protected function html(string $html, int $status = 200)
    {
        return response($html, $status)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    protected function currentHost(): string
    {
        return $this->canon((string) ($_SERVER['HTTP_HOST'] ?? ''));
    }

    protected function canon(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);
        return strtolower(rtrim($host, '/'));
    }

    protected function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) === 'on')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    protected function loadFunctions(): void
    {
        require_once dirname(__DIR__, 2) . '/functions/sso.php';
    }
}
