<?php

declare(strict_types=1);

namespace think\site\service;

use think\site\model\Site;
use think\site\model\SiteDomain;

/**
 * 站点解析：按域名或站点编码找到站点，并校验可用性.
 *
 * 不改写 pathinfo、不依赖 URL 前缀：站点由"绑定域名"或"登录时选择的站点编码"确定，
 * 登录后写入会话。请求头 / 查询参数不能决定站点，否则任何人都能伪造站点身份。
 * @class SiteResolver
 */
final class SiteResolver
{
    /**
     * 按绑定域名查站点，未绑定返回 null.
     */
    public static function byHost(string $host): ?Site
    {
        $host = strtolower(trim($host));
        if (str_contains($host, ':')) {
            $host = strstr($host, ':', true);
        }
        if ($host === '') {
            return null;
        }
        $domain = SiteDomain::mk()->where(['domain' => $host, 'status' => 1])->findOrEmpty();
        return $domain->isEmpty() ? null : static::byId(strval($domain['site_id']));
    }

    /**
     * 按站点编码查站点.
     */
    public static function byCode(string $code): ?Site
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }
        $site = Site::mk()->where(['code' => $code, 'is_deleted' => 0])->findOrEmpty();
        return $site->isEmpty() ? null : $site;
    }

    /**
     * 按站点 ID 查站点.
     */
    public static function byId(string $id): ?Site
    {
        if ($id === '') {
            return null;
        }
        $site = Site::mk()->where(['id' => $id, 'is_deleted' => 0])->findOrEmpty();
        return $site->isEmpty() ? null : $site;
    }

    /**
     * 校验站点是否可用，不可用时返回原因，可用返回空字符串.
     */
    public static function reason(?Site $site): string
    {
        if ($site === null) {
            return '站点不存在或已被注销！';
        }
        if (intval($site['status']) !== 1) {
            return '该站点已被停用，禁止访问！';
        }
        if (!$site->isAvailable()) {
            return '该站点服务已到期，禁止访问！';
        }
        return '';
    }

    /**
     * 绑定站点上下文.
     */
    public static function bind(Site $site): void
    {
        SiteContext::bind(strval($site['id']), strval($site['code']));
    }
}
