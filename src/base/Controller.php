<?php

declare(strict_types=1);

namespace think\site\base;

use think\exception\HttpException;
use think\site\service\SiteContext;

/**
 * 站点控制器基类：分站插件的控制器继承它.
 *
 * 即使守卫被绕过(例如控制器被错误地挂到别的应用)，没有站点上下文也直接 403，不会无隔离地执行.
 * @class Controller
 */
abstract class Controller extends \think\admin\base\Controller
{
    /**
     * 当前站点 ID.
     * @var string
     */
    protected $siteId = '';

    protected function initialize()
    {
        if (!SiteContext::isBound()) {
            throw new HttpException(403, '当前请求未绑定站点，禁止访问！');
        }
        $this->siteId = SiteContext::id();
        // 站点归属只能由服务端上下文决定：批量更新类接口(mSave 等)会把请求参数直接写库，
        // 这里统一丢弃客户端提交的 site_id，防止把记录转移到别的站点。
        $this->request->withPost(array_diff_key($this->request->post(), ['site_id' => 1]));
        $this->request->withGet(array_diff_key($this->request->get(), ['site_id' => 1]));
        $this->get = $this->request->get();
    }
}
