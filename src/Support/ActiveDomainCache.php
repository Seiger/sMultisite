<?php namespace Seiger\sMultisite\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Cache;
use Seiger\sMultisite\Models\sMultisite;

/**
 * Cache exact-host active-domain lookups without serializing model dates.
 * Generation keys prevent in-flight reads from restoring invalidated entries.
 *
 * @since 1.2.0
 */
final class ActiveDomainCache
{
    /** @var \WeakMap<\Illuminate\Database\Connection, string>|null Isolate ephemeral SQLite databases. */
    private static ?\WeakMap $memoryDatabases = null;

    /**
     * Scope generations to the actual database and prefixed package table.
     *
     * @param \Illuminate\Database\Connection $connection
     * @return string
     * @since 1.2.0
     */
    private static function key($connection): string
    {
        $database = (string)$connection->getDatabaseName();
        $memoryScope = null;
        if ($connection->getDriverName() === 'sqlite'
            && ($database === '' || str_contains($database, ':memory:') || str_contains($database, 'mode=memory'))) {
            self::$memoryDatabases ??= new \WeakMap();
            $memoryScope = self::$memoryDatabases[$connection] ??= bin2hex(random_bytes(16));
        }
        return 'sMultisite.active-domain.v1.' . hash('sha256', serialize([
            $connection->getName(), $connection->getConfig('driver'), $connection->getConfig('host'),
            $connection->getConfig('port'), $connection->getConfig('unix_socket'),
            $connection->getDatabaseName(), $connection->getTablePrefix(), $memoryScope,
        ]));
    }

    /**
     * Observe model, builder and raw SQL writes, including comments and CTE prefixes.
     * Clear again after the outer transaction ends to discard concurrent old reads.
     *
     * @param \Illuminate\Contracts\Events\Dispatcher $events
     * @return void
     * @since 1.2.0
     */
    public static function listen($events): void
    {
        $dirty = [];
        foreach ([\Illuminate\Database\Events\MigrationEnded::class,
            \Illuminate\Database\Events\MigrationsEnded::class] as $event) {
            $events->listen($event, static function (): void {
                $connection = (new sMultisite())->getConnection();
                Cache::forget(self::key($connection) . '.table_ready');
                Cache::forget(self::key($connection));
            });
        }
        $events->listen(QueryExecuted::class, static function ($event) use (&$dirty): void {
            $table = preg_quote($event->connection->getTablePrefix() . 's_multisites', '~');
            if (!preg_match('~\b(?:insert|update|delete|replace|truncate|create|alter|drop|rename)\b~i', $event->sql)
                || !preg_match('~(?<![a-z0-9_])' . $table . '(?![a-z0-9_])~i', $event->sql)) {
                return;
            }
            if (preg_match('~\b(?:create|alter|drop|rename)\b~i', $event->sql)) {
                Cache::forget(self::key($event->connection) . '.table_ready');
            }
            Cache::forget(self::key($event->connection));
            if ($event->connection->transactionLevel() > 0) {
                $dirty[spl_object_id($event->connection)] = true;
            }
        });
        foreach ([TransactionCommitted::class, TransactionRolledBack::class] as $event) {
            $events->listen($event, static function ($event) use (&$dirty): void {
                $id = spl_object_id($event->connection);
                if (isset($dirty[$id]) && $event->connection->transactionLevel() === 0) {
                    Cache::forget(self::key($event->connection));
                    Cache::forget(self::key($event->connection) . '.table_ready');
                    unset($dirty[$id]);
                }
            });
        }
    }

    /**
     * Cache only a successful schema check, scoped to the model's database.
     * Missing tables are checked again on the next call, including installation
     * in the same process. Transactions never publish uncommitted schema state.
     * Read the shared readiness entry on each call so direct Cache::flush() takes
     * effect even when the backend emits no flush event. Migrations and observed
     * DDL remove the positive entry; TTL bounds externally changed schemas.
     *
     * @return bool Whether the multisite table currently exists.
     * @since 1.2.0
     */
    public static function tableReady(): bool
    {
        $connection = (new sMultisite())->getConnection();
        $key = self::key($connection) . '.table_ready';
        $transaction = $connection->transactionLevel() > 0;
        if (!$transaction && Cache::get($key) === true) {
            return true;
        }
        $ready = $connection->getSchemaBuilder()->hasTable('s_multisites');
        if ($ready && !$transaction) {
            Cache::put($key, true, 300);
        }
        return $ready;
    }

    /**
     * Resolve the caller's unchanged host, including OnLoadSettings setHost overrides.
     * Cache misses in an envelope; bypass shared state inside transactions. A five
     * minute TTL also bounds staleness for writes made outside this application.
     *
     * CLI may have no host: retain the original whereDomain(null) lookup and
     * bypass shared host caching rather than treating null as an empty domain.
     * Custom global scopes and retrieved observers retain their original database path.
     *
     * @param string|null $host Exact host used by the existing domain query, or null in CLI.
     * @return sMultisite|null
     * @since 1.2.0
     */
    public static function find(?string $host): ?sMultisite
    {
        $model = new sMultisite();
        $connection = $model->getConnection();
        $read = static fn () => sMultisite::whereDomain($host)->whereActive(1)->first();
        // Custom scopes/observers may depend on context outside the exact host.
        $events = $model->getEventDispatcher();
        if ($host === null || $connection->transactionLevel() > 0
            || $model->getGlobalScopes() !== []
            || ($events && $events->hasListeners('eloquent.retrieved: ' . sMultisite::class))) {
            return $read();
        }

        $key = self::key($connection);
        $generation = Cache::remember($key, 300, static fn () => bin2hex(random_bytes(16)));
        $entryKey = $key . '.' . $generation . '.' . hash('sha256', $host);
        $entry = Cache::get($entryKey);
        if (is_array($entry) && array_key_exists('attributes', $entry)) {
            return $entry['attributes'] === null ? null
                : $model->newFromBuilder($entry['attributes'], $connection->getName());
        }

        // Return the queried model on a miss so retrieved observers fire only once.
        $domain = $read();
        Cache::put($entryKey, ['attributes' => $domain?->getRawOriginal()], 300);
        return $domain;
    }

}
