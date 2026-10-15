<?php

declare(strict_types=1);

namespace think\site;

use think\site\model\Site;
use think\site\model\SiteAuth;
use think\site\model\SiteUser;

/**
 * 新站点初始化：默认角色与站点超级管理员.
 * @class SiteInit
 */
final class SiteInit
{
    /**
     * 密码最小长度.
     */
    public const MIN_PASSWORD = 8;

    /**
     * 初始化站点，返回创建的超管用户.
     * @throws \InvalidArgumentException 密码为空或过短
     */
    public static function init(Site $site, string $username, string $password, string $nickname = ''): SiteUser
    {
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('站点管理员账号不能为空！');
        }
        if (strlen($password) < self::MIN_PASSWORD) {
            throw new \InvalidArgumentException('站点管理员密码不能少于' . self::MIN_PASSWORD . '位！');
        }
        return SiteContext::with(strval($site['id']), strval($site['code']), static function () use ($site, $username, $password, $nickname): SiteUser {
            $auth = SiteAuth::mk();
            $auth->save(['title' => '运营管理', 'desc' => '站点基础运营角色', 'sort' => 100, 'status' => 1]);
            $user = SiteUser::mk();
            $user->save([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'nickname' => $nickname !== '' ? $nickname : strval($site['name']) . '管理员',
                'authorize' => [strval($auth['id'])],
                'is_super' => 1,
                'status' => 1,
                'is_deleted' => 0,
            ]);
            return $user;
        });
    }
}
