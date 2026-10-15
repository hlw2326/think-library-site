<?php

declare(strict_types=1);

namespace think\site\middleware;

use think\admin\service\AdminService;
use think\admin\service\NodeService;
use think\App;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use think\site\Service;
use think\site\service\SiteAccess;
use think\site\service\SiteContext;
use think\site\service\SiteRegistry;
use think\site\service\SiteResolver;

/**
 * 站点守卫：挂在 route 中间件最前面(早于总控 RbacAccess).
 *
 * 规则(默认拒绝)：
 *  - 域名已绑定站点的请求，只能访问站点端控制器和分站插件，其余应用(含总控后台)一律 403；
 *  - 站点端控制器、分站插件必须有有效的站点登录，且节点已授权，否则拒绝；
 *  - 总控管理类控制器(site 应用的 manage.*)与 passport 不走站点登录，前者仍由总控 RBAC 把关。
 * @class SiteGuard
 */
class SiteGuard
{
    /**
     * 本次请求注册的权限检查回调编号，用于请求结束后释放.
     */
    private static ?int $callable = null;

    public function __construct(protected App $app) {}

    /**
     * 释放本次请求注册的权限回调(常驻进程里静态属性会跨请求保留).
     */
    public static function release(): void
    {
        if (self::$callable !== null) {
            AdminService::removeCheckCallable(self::$callable);
            self::$callable = null;
        }
        SiteContext::clear();
    }

    public function handle(Request $request, \Closure $next): Response
    {
        static::release();
        $app = strtolower($this->app->http->getName());
        $ctl = strtolower(strval($request->controller()));

        // 域名绑定站点：先绑定，后续默认拒绝非站点应用
        $bound = $this->bindByHost($request);

        $isSiteApp = $app === Service::CODE;
        $isPassport = $isSiteApp && $ctl === 'passport';
        $isManage = $isSiteApp && str_starts_with($ctl, 'manage.');
        $isConsole = $isSiteApp && str_starts_with($ctl, 'console.');
        $isPlugin = SiteRegistry::isSite($app);

        if ($isPassport) {
            return $next($request);
        }
        if ($isManage) {
            // 总控管理页面：站点域名上禁止访问，其余交给总控 RBAC
            $bound && $this->deny('当前域名不能访问总控管理页面！');
            return $next($request);
        }
        if (!$isConsole && !$isPlugin) {
            // 页面脚本接口是公共资源，站点页面同样需要加载
            if ($bound && !$this->isPublicAsset($app, $ctl)) {
                $this->deny('当前域名仅限访问本站点内容！');
            }
            return $next($request);
        }

        // 站点端控制台 / 分站插件：必须有有效站点登录
        $this->checkOrigin($request);
        $user = SiteAccess::resume();
        if ($user === null) {
            $this->unauthorized($request);
        }
        if ($bound && SiteContext::id() !== $bound) {
            // 域名绑定的站点与会话站点不一致
            SiteAccess::logout();
            $this->unauthorized($request);
        }
        // 与总控 RBAC 使用同一套节点编码，驼峰控制器名会转成下划线
        $node = NodeService::getCurrent();
        if (!SiteAccess::check($user, $node)) {
            $this->deny('没有访问权限！');
        }
        // 视图里的 auth() 走 AdminService::check，让它按站点授权判断
        self::$callable = AdminService::registerCheckCallable(
            static fn(string $node): bool => SiteAccess::check($user, $node)
        );
        return $next($request);
    }

    /**
     * 写操作的来源校验(防跨站表单提交)：浏览器在跨站 POST 时必带 Origin(或 Referer)，
     * 其主机必须与当前请求主机一致。两者都没有的请求不是浏览器发起的跨站表单，放行。
     * 这是纵深防御之一，不替代会话 Cookie 的 SameSite 设置。
     */
    private function checkOrigin(Request $request): void
    {
        if (in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $source = strval($request->header('origin', '')) ?: strval($request->header('referer', ''));
        if ($source === '') {
            return;
        }
        $from = strtolower(strval(parse_url($source, PHP_URL_HOST)));
        if ($from === '' || $from !== strtolower(strval($request->host(true)))) {
            $this->deny('请求来源不合法，禁止跨站提交！');
        }
    }

    /**
     * 按请求域名绑定站点，返回站点 ID，未绑定返回空字符串.
     * 域名表不存在等数据库异常时视为未绑定，避免拖垮总控后台。
     */
    private function bindByHost(Request $request): string
    {
        try {
            $site = SiteResolver::byHost(strval($request->host(true)));
        } catch (\Throwable) {
            return '';
        }
        if ($site === null) {
            return '';
        }
        $reason = SiteResolver::reason($site);
        $reason === '' || $this->deny($reason);
        SiteResolver::bind($site);
        return strval($site['id']);
    }

    /**
     * 站点页面需要加载的公共资源(前端脚本变量接口).
     */
    private function isPublicAsset(string $app, string $ctl): bool
    {
        return $app === 'admin' && $ctl === 'api.plugs' && strtolower($this->app->request->action()) === 'script';
    }

    /**
     * 未登录：Ajax 返回 JSON，页面跳转站点登录.
     */
    private function unauthorized(Request $request): never
    {
        $url = sysuri('site/passport/index');
        if ($request->isAjax() || $request->isJson()) {
            throw new HttpResponseException(json(['code' => 0, 'info' => lang('请重新登录！'), 'url' => $url]));
        }
        throw new HttpResponseException(redirect($url));
    }

    /**
     * 拒绝访问.
     */
    private function deny(string $message): never
    {
        $request = $this->app->request;
        if ($request->isAjax() || $request->isJson()) {
            throw new HttpResponseException(json(['code' => 0, 'info' => lang($message)], 403));
        }
        throw new HttpResponseException(response(lang($message), 403, [], 'html'));
    }
}
