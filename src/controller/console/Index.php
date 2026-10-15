<?php

declare(strict_types=1);

namespace think\site\controller\console;

use think\site\Controller;
use think\site\model\Site;
use think\site\SiteAccess;
use think\site\SiteMenu;

/**
 * 站点控制台首页.
 * @class Index
 */
class Index extends Controller
{
    /**
     * 控制台外壳(菜单 + 内容区).
     */
    public function index()
    {
        $user = SiteAccess::resume() ?? [];
        $this->title = '站点控制台';
        $this->menus = SiteMenu::tree($user);
        $this->user = $user;
        $this->site = Site::mk()->where(['id' => $this->siteId])->findOrEmpty()->toArray();
        $this->fetch();
    }

    /**
     * 站点首页.
     */
    public function home()
    {
        $this->title = '站点首页';
        $this->site = Site::mk()->where(['id' => $this->siteId])->findOrEmpty()->toArray();
        $this->fetch();
    }
}
