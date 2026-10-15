<?php

declare(strict_types=1);

namespace think\site\phinx;

use Phinx\Db\Table;
use Phinx\Util\Literal;
use think\admin\database\migration\PhinxExtend;
use think\admin\database\migration\PhinxSchema;

/**
 * Site table migration helpers using UUIDv7 primary keys.
 *
 * Scoped tables receive a non-null site_id column and index. Global tables use
 * the same UUID primary key without site_id.
 * @class PhinxExtendV2
 */
final class PhinxExtendV2
{
    private static bool $scoped = true;

    /**
     * Create or upgrade a global table.
     */
    public static function global(Table $table, array $fields, array $indexes = [], bool $force = false): Table
    {
        $scoped = self::$scoped;
        self::$scoped = false;
        try {
            return self::upgrade($table, $fields, $indexes, $force);
        } finally {
            self::$scoped = $scoped;
        }
    }

    /**
     * Create or upgrade a site-scoped table.
     */
    public static function upgrade(Table $table, array $fields, array $indexes = [], bool $force = false): Table
    {
        if (!$table instanceof \think\migration\db\Table) {
            throw new \InvalidArgumentException('PhinxExtendV2 requires a table created by Migrator::table().');
        }

        $table->setId(false)->setPrimaryKey('id');
        [$adapter] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $driver = PhinxSchema::driver($adapter);
        $isDryRun = PhinxSchema::isDryRun($adapter);
        if ($driver === 'pgsql' && !$isDryRun) {
            self::ensureUuidv7($adapter);
        }

        $names = array_column($fields, 0);
        if (self::$scoped && !in_array('site_id', $names, true)) {
            array_unshift($fields, self::ref('site_id', false, '所属站点'));
            array_unshift($indexes, ['site_id']);
        }
        if (!in_array('id', $names, true)) {
            $options = ['null' => false, 'comment' => '主键UUID'];
            if ($driver === 'pgsql') {
                $options['default'] = Literal::from('uuidv7()');
            }
            array_unshift($fields, ['id', 'uuid', $options]);
        }

        $result = PhinxExtend::upgrade($table, $fields, $indexes, $force);
        if (self::$scoped && $driver === 'pgsql' && !$isDryRun && self::rlsEnabled()) {
            self::enableRls($table, $adapter);
        }
        return $result;
    }

    /**
     * Define a UUID foreign key column.
     * @return array{0:string,1:string,2:array<string,mixed>}
     */
    public static function ref(string $name, bool $nullable = true, string $comment = ''): array
    {
        return [$name, 'uuid', ['default' => null, 'null' => $nullable, 'comment' => $comment]];
    }

    private static function enableRls(Table $table, $adapter): void
    {
        [, $name] = PhinxSchema::connection($table->getAdapter(), $table->getName());
        $quotedTable = $adapter->quoteTableName($name);
        $quotedPolicy = $adapter->quoteColumnName('rls_site_' . $table->getName());
        $condition = "site_id = NULLIF(current_setting('app.current_site_id', true), '')::uuid";
        $adapter->execute("ALTER TABLE {$quotedTable} ENABLE ROW LEVEL SECURITY");
        $adapter->execute("ALTER TABLE {$quotedTable} FORCE ROW LEVEL SECURITY");
        $adapter->execute("DROP POLICY IF EXISTS {$quotedPolicy} ON {$quotedTable}");
        $adapter->execute("CREATE POLICY {$quotedPolicy} ON {$quotedTable} USING ({$condition}) WITH CHECK ({$condition})");
    }

    private static function rlsEnabled(): bool
    {
        return function_exists('config') && (bool)config('site.rls', false);
    }

    /**
     * PostgreSQL 18 provides uuidv7(). Install the same function on earlier
     * supported PostgreSQL releases when the application database lacks it.
     */
    private static function ensureUuidv7($adapter): void
    {
        $exists = $adapter->fetchRow("SELECT 1 AS ok FROM pg_proc WHERE proname = 'uuidv7' LIMIT 1");
        if (!empty($exists)) {
            return;
        }
        $adapter->execute(<<<'SQL'
CREATE FUNCTION uuidv7() RETURNS uuid AS $$
DECLARE
    ts bytea := substring(int8send((extract(epoch FROM clock_timestamp()) * 1000)::bigint) FROM 3);
    raw bytea := decode(replace(gen_random_uuid()::text, '-', ''), 'hex');
BEGIN
    raw := overlay(raw PLACING ts FROM 1 FOR 6);
    raw := set_byte(raw, 6, (get_byte(raw, 6) & 15) | 112);
    raw := set_byte(raw, 8, (get_byte(raw, 8) & 63) | 128);
    RETURN encode(raw, 'hex')::uuid;
END
$$ LANGUAGE plpgsql VOLATILE
SQL);
    }
}
