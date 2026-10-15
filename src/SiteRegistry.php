<?php

declare(strict_types=1);

namespace think\site;

use think\admin\facade\Plugin;

/**
 * 分站插件注册表：按服务类是否继承 SitePlugin 识别分站插件.
 * @class SiteRegistry
 */
final class SiteRegistry
{
    /**
     * 全部分站插件，键为插件编码.
     * @return array<string, array>
     */
    public static function all(): array
    {
        $list = [];
        foreach (Plugin::get() ?? [] as $code => $addon) {
            if (static::isSiteService(strval($addon['service'] ?? ''))) {
                $list[$code] = $addon;
            }
        }
        return $list;
    }

    /**
     * 指定编码是否为分站插件.
     */
    public static function isSite(string $code): bool
    {
        $addon = Plugin::get($code);
        return !empty($addon) && static::isSiteService(strval($addon['service'] ?? ''));
    }

    /**
     * 服务类是否继承 SitePlugin.
     */
    private static function isSiteService(string $service): bool
    {
        return $service !== '' && class_exists($service) && is_subclass_of($service, SitePlugin::class);
    }
}
