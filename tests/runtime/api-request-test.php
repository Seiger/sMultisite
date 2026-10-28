<?php
/** Isolated CMS container for API event regression tests. */
function evo() { return $GLOBALS['apiTestApp']; }
require __DIR__ . '/bootstrap.php';
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
/** Supply only the CMS configuration and access hooks used by this test. */
$app = new class extends Container {
    public int $documentIdentifier = 1;
    public int $denials = 0;
    /** Return isolated configuration without bootstrapping a site. */
    public function getConfig($name, $default = null) { return $default; }
    /** Record an access denial without emitting a response. */
    public function sendErrorPage(): void { $this->denials++; }
};
$GLOBALS['apiTestApp'] = $app;
$events = new Dispatcher($app);
$app->instance('events', $events);
Facade::setFacadeApplication($app);
if (!class_exists('Event', false)) { class_alias(Illuminate\Support\Facades\Event::class, 'Event'); }
require dirname(__DIR__, 2) . '/plugins/sMultisitePlugin.php';
$events->dispatch('evolution.OnWebPageInit', [[]]);
if ($app->bound('request') || $app->denials !== 0) { throw new RuntimeException('API event must not bind a request or deny access'); }

$app->instance('request', Illuminate\Http\Request::create('/private/'));
$app->instance('cache', new Illuminate\Cache\Repository(new Illuminate\Cache\ArrayStore()));
$_SERVER['REQUEST_URI'] = '/private/';
$events->dispatch('evolution.OnWebPageInit');
if ($app->denials !== 1) { throw new RuntimeException('Bound HTTP request must still deny a foreign resource'); }
$app['cache']->put('sMultisite-default-resources', [1]);
$events->dispatch('evolution.OnWebPageInit');
if ($app->denials !== 1) { throw new RuntimeException('Allowed HTTP resource must remain accessible'); }
echo "API and HTTP resource guards passed\n";
