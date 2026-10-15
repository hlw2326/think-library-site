<?php

declare(strict_types=1);

namespace think\site;

/**
 * 当前请求的站点上下文.
 *
 * 状态保存在 sysvar 中，随请求结束自动清理。常驻进程(队列、worker)必须在每个任务
 * 开始前显式 bind()，结束后 clear()，否则会把上一个任务的站点带到下一个任务。
 * @class SiteContext
 */
final class SiteContext
{
    private const KEY_ID = 'think.site.id';

    private const KEY_CODE = 'think.site.code';

    /**
     * 绑定当前站点.
     */
    public static function bind(string $id, string $code = ''): void
    {
        sysvar(self::KEY_ID, $id);
        sysvar(self::KEY_CODE, $code);
        self::syncRls($id);
    }

    /**
     * 开启 PostgreSQL 行级安全时，把站点同步到数据库会话变量(参数绑定，不拼接 SQL).
     * 仅 rls 开关打开且连接是 PostgreSQL 时执行；未验证过真实 PostgreSQL。
     */
    private static function syncRls(string $id): void
    {
        if (!function_exists('config') || !config('site.rls', false)) {
            return;
        }
        $db = \think\admin\facade\Library::$sapp->db->connect();
        if (strtolower(strval($db->getConfig('type'))) === 'pgsql') {
            $db->execute('SELECT set_config(?, ?, false)', ['app.current_site_id', $id]);
        }
    }

    /**
     * 清除当前站点绑定.
     */
    public static function clear(): void
    {
        // sysvar() 传 null 是读取而不是清除，必须写入空字符串
        sysvar(self::KEY_ID, '');
        sysvar(self::KEY_CODE, '');
        self::syncRls('');
    }

    /**
     * 当前站点 ID，未绑定时为空字符串.
     */
    public static function id(): string
    {
        return strval(sysvar(self::KEY_ID) ?? '');
    }

    /**
     * 当前站点代码，未绑定时为空字符串.
     */
    public static function code(): string
    {
        return strval(sysvar(self::KEY_CODE) ?? '');
    }

    /**
     * 是否已绑定站点.
     */
    public static function isBound(): bool
    {
        return self::id() !== '';
    }

    /**
     * 要求已绑定站点，未绑定直接抛出异常，不允许静默放行.
     * @throws \RuntimeException
     */
    public static function require(): string
    {
        $id = self::id();
        if ($id === '') {
            throw new \RuntimeException('当前请求未绑定站点，禁止访问站点数据！');
        }
        return $id;
    }

    /**
     * 在指定站点下执行回调，结束后恢复原绑定.
     * @template T
     * @param callable():T $call
     * @return T
     */
    public static function with(string $id, string $code, callable $call)
    {
        [$oldId, $oldCode] = [self::id(), self::code()];
        self::bind($id, $code);
        try {
            return $call();
        } finally {
            $oldId === '' ? self::clear() : self::bind($oldId, $oldCode);
        }
    }
}
