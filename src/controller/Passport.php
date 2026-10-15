<?php

declare(strict_types=1);

namespace think\site\controller;

use think\admin\base\Controller;
use think\site\model\Site;
use think\site\model\SiteUser;
use think\site\SiteAccess;
use think\site\SiteContext;
use think\site\SiteResolver;

/**
 * 站点登录入口.
 *
 * 站点由"绑定域名"或"登录表单里的站点编码"确定。不接受请求头 / 查询参数指定站点。
 * @class Passport
 */
class Passport extends Controller
{
    private const MAX_FAIL = 5;

    private const LOCK_SECONDS = 600;

    /**
     * 站点登录.
     */
    public function index()
    {
        if ($this->request->isGet()) {
            if (SiteAccess::resume() !== null) {
                $this->redirect(sysuri('site/console.index/index'));
            }
            $this->title = '站点登录';
            $this->hostSite = $this->hostSite();
            $this->fetch();
        }
        $this->login();
    }

    /**
     * 退出登录.
     */
    public function out()
    {
        SiteAccess::logout();
        $this->success('退出登录成功！', sysuri('site/passport/index'));
    }

    /**
     * 处理登录提交.
     */
    private function login(): void
    {
        $data = $this->_vali([
            'username.require' => '登录账号不能为空！',
            'password.require' => '登录密码不能为空！',
        ]);
        $site = $this->hostSite() ?? SiteResolver::byCode(strval($this->request->post('code', '')));
        $reason = SiteResolver::reason($site);
        $reason === '' || $this->error($reason);

        $key = 'site_login_fail_' . md5($this->request->ip() . '|' . $site['id'] . '|' . strtolower($data['username']));
        if (intval(cache($key)) >= self::MAX_FAIL) {
            $this->error('登录失败次数过多，请稍后再试！');
        }

        SiteResolver::bind($site);
        $user = SiteUser::mk()->where(['username' => $data['username'], 'is_deleted' => 0])->findOrEmpty();
        // 账号不存在与密码错误给同样的提示，避免枚举账号
        if ($user->isEmpty() || !password_verify(strval($data['password']), strval($user['password']))) {
            cache($key, intval(cache($key)) + 1, self::LOCK_SECONDS);
            $this->error('登录账号或密码错误，请重新输入！');
        }
        if (intval($user['status']) !== 1) {
            $this->error('账号已经被禁用，请联系管理员！');
        }
        $user->save([
            'login_ip' => $this->request->ip(),
            'login_at' => date('Y-m-d H:i:s'),
            'login_num' => intval($user['login_num']) + 1,
        ]);
        cache($key, null);
        SiteAccess::login($user, strval($site['id']));
        $this->success('登录成功！', sysuri('site/console.index/index'));
    }

    /**
     * 请求域名绑定的站点.
     */
    private function hostSite(): ?Site
    {
        return SiteResolver::byHost(strval($this->request->host(true)));
    }
}
