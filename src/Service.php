<?php

declare(strict_types=1);

namespace think\site;

use think\admin\facade\Plugin;
use think\App;
use think\site\command\SiteCheck;

/**
 * 站点库服务：注册守卫、命令，并提供总控侧的站点管理菜单.
 *
 * 本服务自身不是分站插件(不继承 SitePlugin)：
 *  - manage.* 控制器给总控管理站点和域名，走总控 RBAC；
 *  - passport / console.* 是站点端入口，由 SiteGuard 用站点登录把关。
 * @class Service
 */
class Service extends Plugin
{
    /**
     * 应用编码，同时是站点端入口的 URL 首段.
     */
    public const CODE = 'site';

    protected $appCode = self::CODE;

    protected $appName = '多站点';

    protected $package = 'hlw2326/think-site-library';

    /**
     * 总控后台菜单：站点与域名管理.
     */
    public static function menu(): array
    {
        return [
            [
                'name' => '站点管理',
                'subs' => [
                    ['name' => '站点列表', 'icon' => 'layui-icon layui-icon-template-1', 'node' => 'site/manage.index/index'],
                    ['name' => '站点域名', 'icon' => 'layui-icon layui-icon-website', 'node' => 'site/manage.domain/index'],
                ],
            ],
        ];
    }

    public function boot(): void
    {
        $this->commands([SiteCheck::class]);
        if ($this->app->runningInConsole()) {
            return;
        }
        // 守卫必须早于总控 RbacAccess：boot 在所有服务 register 之后执行，unshift 保证排在最前
        $this->app->middleware->unshift(SiteGuard::class, 'route');
        // 请求前后都释放守卫注册的静态回调，避免 worker 常驻进程里跨请求残留
        $release = static function (): void {
            SiteGuard::release();
        };
        $this->app->event->listen('HttpRun', $release);
        $this->app->event->listen('HttpEnd', $release);
    }
}
