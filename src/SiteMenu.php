<?php

declare(strict_types=1);

namespace think\site;

use think\admin\extend\DataExtend;
use think\admin\service\NodeService;

/**
 * 站点端菜单：站点控制台自带菜单 + 各分站插件的 menu()，按站点授权过滤.
 * 返回结构与总控 MenuService::getTree() 一致，可直接复用后台外壳模板.
 * @class SiteMenu
 */
final class SiteMenu
{
    /**
     * 站点控制台自带菜单项.
     */
    public static function core(): array
    {
        return [
            [
                'name' => '站点管理',
                'icon' => 'layui-icon layui-icon-set',
                'subs' => [
                    ['name' => '站点首页', 'icon' => 'layui-icon layui-icon-home', 'node' => 'site/console.index/home'],
                    ['name' => '站点用户', 'icon' => 'layui-icon layui-icon-username', 'node' => 'site/console.user/index'],
                    ['name' => '站点角色', 'icon' => 'layui-icon layui-icon-vercode', 'node' => 'site/console.auth/index'],
                ],
            ],
        ];
    }

    /**
     * 当前用户可见的菜单树.
     * @param array $user SiteAccess::resume() 返回的用户数据
     */
    public static function tree(array $user): array
    {
        $flat = [];
        $seq = 0;
        $walk = function (array $items, string $pid) use (&$walk, &$flat, &$seq, $user): void {
            foreach ($items as $item) {
                $id = strval(++$seq);
                $node = strval($item['node'] ?? '');
                $full = $node === '' ? '' : NodeService::fullNode($node);
                $flat[] = [
                    'id' => $id,
                    'pid' => $pid,
                    'title' => strval($item['name'] ?? $item['title'] ?? ''),
                    'icon' => strval($item['icon'] ?? ''),
                    'node' => $full,
                    'url' => $full === '' ? '#' : sysuri($full),
                    'sub' => [],
                ];
                if (!empty($item['subs'])) {
                    $walk($item['subs'], $id);
                }
            }
        };
        $walk(static::core(), '');
        foreach (SiteRegistry::all() as $code => $addon) {
            $service = strval($addon['service'] ?? '');
            if (!method_exists($service, 'menu') || empty($menus = $service::menu())) {
                continue;
            }
            $id = strval(++$seq);
            $flat[] = ['id' => $id, 'pid' => '', 'title' => strval($addon['name'] ?: $code), 'icon' => '', 'node' => '', 'url' => '#', 'sub' => []];
            $walk($menus, $id);
        }
        return static::filter(DataExtend::arr2tree($flat, 'id', 'pid'), $user);
    }

    /**
     * 过滤：叶子必须有授权，分组下没有可见叶子则整组隐藏.
     */
    private static function filter(array $menus, array $user): array
    {
        foreach ($menus as $key => &$menu) {
            if (!empty($menu['sub'])) {
                $menu['sub'] = static::filter($menu['sub'], $user);
            }
            if (!empty($menu['sub'])) {
                $menu['url'] = '#';
            } elseif ($menu['node'] === '' || !SiteAccess::check($user, $menu['node'])) {
                unset($menus[$key]);
            }
        }
        return array_values($menus);
    }
}
