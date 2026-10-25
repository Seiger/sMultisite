<?php

use Illuminate\Support\Facades\Cache;
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.2.0
 */
function evo()
{
    return $GLOBALS['evo'];
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.2.0
 */
function ms_multisite_table_ready()
{
    return \Seiger\sMultisite\Support\ActiveDomainCache::tableReady();
}
require __DIR__ . '/bootstrap.php';
use Seiger\sMultisite\Support\ActiveDomainCache as C;
use Seiger\sMultisite\Models\sMultisite as M;
$app = new Illuminate\Container\Container();
Illuminate\Support\Facades\Facade::setFacadeApplication($app);
$events = new Illuminate\Events\Dispatcher($app);
$app->instance('events', $events);
$app->instance('cache', new Illuminate\Cache\Repository(new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir() . '/ewt-domain-cache-check-' . getmypid())));
$db = new Illuminate\Database\Capsule\Manager();
$db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'evo_']);
$db->setEventDispatcher($events);
$db->setAsGlobal();
$db->bootEloquent();
$c = $db->getConnection();
$c->statement('CREATE TABLE evo_s_multisites (id INTEGER PRIMARY KEY, domain TEXT, active INTEGER, resource INTEGER, "key" TEXT, site_name TEXT, site_start INTEGER, error_page INTEGER, unauthorized_page INTEGER, site_color TEXT, created_at TEXT, updated_at TEXT)');
C::listen($events);
foreach (['a.test', 'b.test'] as $i => $host) {
    $c->table('s_multisites')->insert(['id' => $i + 1, 'domain' => $host, 'active' => 1, 'resource' => $i + 10, 'key' => 'site' . $i, 'site_name' => 'Site ' . $i, 'site_start' => $i + 20, 'error_page' => 4, 'unauthorized_page' => 5, 'site_color' => 'blue']);
}
/**
 * Support isolated package assertions without a site bootstrap.
 *
 * @since 1.2.0
 */
function check($ok, $label)
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "OK {$label}\n";
}
$c->enableQueryLog();
check(C::find('a.test')->site_start === 20, 'cold lookup and integer casts');
$c->flushQueryLog();
check(C::find('a.test')->key === 'site0' && count($c->getQueryLog()) === 0, 'warm lookup skips SQL');
$retrieved = 0;
M::retrieved(static function () use (&$retrieved): void {
    ++$retrieved;
});
C::find('a.test');
C::find('a.test');
check($retrieved === 2, 'retrieved observers run once per lookup');
$events->forget('eloquent.retrieved: ' . M::class);
$decorate = true;
M::retrieved(static function ($model) use (&$decorate): void {
    if ($decorate) {
        $model->site_name = 'decorated:' . $model->site_name;
    }
});
Cache::flush();
$firstDecorated = C::find('a.test')->site_name;
check($firstDecorated === 'decorated:Site 0' && C::find('a.test')->site_name === $firstDecorated, 'retrieved observer transformations run once on original attributes');
$decorate = false;
$events->forget('eloquent.retrieved: ' . M::class);
$c->statement("/* manager edit */ UPDATE evo_s_multisites SET site_name = 'Commented' WHERE id = 1");
check(C::find('a.test')->site_name === 'Commented', 'comment-prefixed writes invalidate');
$c->statement("WITH chosen AS (SELECT 1 AS id) UPDATE evo_s_multisites SET site_name = 'CTE' WHERE id IN (SELECT id FROM chosen)");
check(C::find('a.test')->site_name === 'CTE', 'CTE-prefixed writes invalidate');
$scopes = M::getAllGlobalScopes();
M::addGlobalScope('deny', static fn($query) => $query->whereRaw('0 = 1'));
check(C::find('a.test') === null, 'custom scopes bypass shared domain entries');
M::setAllGlobalScopes($scopes);
C::find('missing.test');
$c->flushQueryLog();
check(C::find('missing.test') === null && count($c->getQueryLog()) === 0, 'missing domain cached');
$c->table('s_multisites')->where('id', 1)->update(['domain' => 'renamed.test', 'site_name' => 'Renamed']);
check(C::find('a.test') === null && C::find('renamed.test')->site_name === 'Renamed', 'rename and fields invalidated');
$c->table('s_multisites')->where('id', 1)->update(['active' => 0]);
check(C::find('renamed.test') === null, 'deactivation invalidated');
$c->table('s_multisites')->where('id', 1)->update(['active' => 1]);
check(C::find('renamed.test') !== null, 'activation invalidated');
$c->beginTransaction();
$c->table('s_multisites')->where('id', 1)->update(['site_name' => 'Uncommitted']);
check(C::find('renamed.test')->site_name === 'Uncommitted', 'transaction bypass');
$c->rollBack();
check(C::find('renamed.test')->site_name === 'Renamed', 'rollback invalidation');
$c->beginTransaction();
$c->table('s_multisites')->where('id', 1)->update(['site_name' => 'Committed']);
$c->commit();
check(C::find('renamed.test')->site_name === 'Committed', 'commit invalidation');
$m = M::find(1);
$m->site_name = 'Model update';
$m->save();
check(C::find('renamed.test')->site_name === 'Model update', 'model editing invalidation');
$m->delete();
check(C::find('renamed.test') === null, 'model deletion invalidation');
$evo = new class
{
    public array $config = [];
    /**
     * Record settings written by the actual package listener.
     *
     * @since 1.2.0
     */
    function setConfig($k, $v)
    {
        $this->config[$k] = $v;
    }
    /**
     * Expose only the controlled configuration used by this isolated regression.
     *
     * @since 1.2.0
     */
    function getConfig($k, $default = null)
    {
        return $this->config[$k] ?? $default;
    }
};
// Exercise the actual listener; the URL facade is unused when no root binding exists.
$processor = new stdClass();
$app->instance('UrlProcessor', $processor);
$source = file_get_contents(dirname(__DIR__, 2) . '/plugins/sMultisitePlugin.php');
$start = strpos($source, "Event::listen('evolution.OnLoadSettings'");
$end = strpos($source, "\n});", $start) + 4;
$listener = substr($source, $start, $end - $start);
eval('use Illuminate\Support\Facades\Event; use Illuminate\Support\Facades\Cache; use EvolutionCMS\Facades\UrlProcessor;' . $listener);
$_SERVER['HTTP_HOST'] = 'unknown.test';
$params = ['config' => ['setHost' => ' b.test ']];
$events->dispatch('evolution.OnLoadSettings', [$params]);
check($evo->config['site_key'] === 'site1' && $evo->config['site_start'] === 21, 'setHost selects trimmed override');
$events->dispatch('evolution.OnLoadSettings', [['config' => []]]);
check($evo->config['site_key'] === 'default' && $evo->config['site_root'] === 0, 'unknown host preserves defaults');
echo "ALL DOMAIN CACHE CHECKS PASSED\n";
unset($_SERVER['HTTP_HOST']);
$events->dispatch('evolution.OnLoadSettings', [['config' => []]]);
check($evo->config['site_key'] === 'default', 'CLI missing host preserves defaults');
$events->dispatch('evolution.OnLoadSettings', [['config' => ['setHost' => ' b.test ']]]);
check($evo->config['site_key'] === 'site1', 'CLI setHost override still works');
$c->table('s_multisites')->insert(['id' => 3, 'domain' => null, 'active' => 1, 'key' => 'null-domain']);
$c->table('s_multisites')->insert(['id' => 4, 'domain' => '', 'active' => 1, 'key' => 'empty-domain']);
check(C::find(null)?->key === 'null-domain', 'null keeps original whereDomain(null) semantics');
check(C::find('')?->key === 'empty-domain', 'empty host remains distinct from null');
$c->flushQueryLog();
C::find(null);
check(count($c->getQueryLog()) === 1, 'hostless lookup bypasses shared host cache');
$c->flushQueryLog();
check(C::tableReady(), 'cold table check');
$c->flushQueryLog();
check(C::tableReady() && count($c->getQueryLog()) === 0, 'warm table check skips SQL');
$events->dispatch(new Illuminate\Database\Events\MigrationsEnded('up'));
$c->flushQueryLog();
C::tableReady();
check(count($c->getQueryLog()) > 0, 'migration completion invalidates');
$events->dispatch(new Illuminate\Database\Events\MigrationEnded(new class extends Illuminate\Database\Migrations\Migration
{
}, 'down'));
$c->flushQueryLog();
C::tableReady();
check(count($c->getQueryLog()) > 0, 'single migration completion invalidates');
Illuminate\Support\Facades\Cache::flush();
$c->flushQueryLog();
C::tableReady();
check(count($c->getQueryLog()) > 0, 'cache flush invalidates');
$c->beginTransaction();
$c->statement('DROP TABLE evo_s_multisites');
check(!C::tableReady(), 'uncommitted drop bypasses positive cache');
$c->rollBack();
check(C::tableReady(), 'rollback restores availability');
$c->statement('DROP TABLE evo_s_multisites');
$c->flushQueryLog();
check(!C::tableReady() && !C::tableReady() && count($c->getQueryLog()) >= 2, 'negative result not cached');
$c->statement('CREATE TABLE evo_s_multisites (id INTEGER PRIMARY KEY)');
check(C::tableReady(), 'create after missing in same process');
$c->flushQueryLog();
check(C::tableReady() && count($c->getQueryLog()) === 0, 'created table warmed');
echo "ALL TABLE READINESS CHECKS PASSED\n";
$other = new Illuminate\Database\SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'evo_', $c->getConfig());
$keyMethod = new ReflectionMethod(C::class, 'key');
$originalKey = $keyMethod->invoke(null, $c);
// Normalize the connection name as well: identity must still follow the ephemeral database.

check($originalKey !== $keyMethod->invoke(null, $other), 'identical in-memory SQLite configurations have isolated cache identities');
