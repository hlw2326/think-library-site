<?php

declare(strict_types=1);

namespace think\site\controller\console;

use think\admin\helper\QueryHelper;
use think\site\base\Controller;
use think\site\model\SiteAuth;
use think\site\model\SiteUser;
use think\site\service\SiteInit;

/**
 * 站点用户管理.
 * @class User
 */
class User extends Controller
{
    /**
     * 站点用户列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        SiteUser::mQuery()->layTable(function () {
            $this->title = '站点用户管理';
        }, function (QueryHelper $query) {
            $query->where(['is_deleted' => 0])->like('username|nickname#username')->equal('status');
        });
    }

    /**
     * 添加站点用户.
     * @auth true
     */
    public function add()
    {
        SiteUser::mForm('form');
    }

    /**
     * 编辑站点用户.
     * @auth true
     */
    public function edit()
    {
        SiteUser::mForm('form');
    }

    /**
     * 修改用户密码.
     * @auth true
     */
    public function pass()
    {
        if ($this->request->isGet()) {
            SiteUser::mForm('pass');
        } else {
            $data = $this->_vali([
                'id.require' => '用户标识不能为空！',
                'password.require' => '登录密码不能为空！',
                'repassword.require' => '重复密码不能为空！',
                'repassword.confirm:password' => '两次输入的密码不一致！',
            ]);
            if (strlen($data['password']) < SiteInit::MIN_PASSWORD) {
                $this->error('登录密码不能少于' . SiteInit::MIN_PASSWORD . '位！');
            }
            $user = SiteUser::mk()->where(['id' => $data['id'], 'is_deleted' => 0])->findOrEmpty();
            $user->isEmpty() && $this->error('用户不存在或无权操作！');
            $user->save(['password' => password_hash($data['password'], PASSWORD_DEFAULT)]);
            $this->success('密码修改成功！', '');
        }
    }

    /**
     * 修改用户状态.
     * @auth true
     */
    public function state()
    {
        $this->guardSuper();
        SiteUser::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除站点用户(软删除).
     * @auth true
     */
    public function remove()
    {
        $this->guardSuper();
        $ids = str2arr(strval($this->request->post('id', '')));
        empty($ids) && $this->error('用户标识不能为空！');
        SiteUser::mk()->whereIn('id', $ids)->update(['is_deleted' => 1]);
        $this->success('删除成功！');
    }

    /**
     * 表单前置处理.
     */
    protected function _form_filter(array &$data)
    {
        if ($this->request->isGet()) {
            $this->auths = SiteAuth::mk()->where(['status' => 1])->order('sort desc,id asc')->select()->toArray();
            $data['authorize'] = $data['authorize'] ?? [];
            return;
        }
        // 禁止通过表单越权：站点、超管标记、密码、删除标记都不接受表单提交
        unset($data['site_id'], $data['is_super'], $data['password'], $data['is_deleted']);
        $id = strval($data['id'] ?? '');
        if ($id === '') {
            $username = trim(strval($data['username'] ?? ''));
            strlen($username) < 4 && $this->error('登录账号不能少于4位字符！');
            $pass = strval($this->request->post('password', ''));
            strlen($pass) < SiteInit::MIN_PASSWORD && $this->error('新用户必须设置不少于' . SiteInit::MIN_PASSWORD . '位的密码！');
            SiteUser::mk()->where(['username' => $username])->count() > 0 && $this->error("账号【{$username}】已存在，请更换！");
            $data['username'] = $username;
            $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
        } else {
            unset($data['username']);
            if (SiteUser::mk()->where(['id' => $id, 'is_super' => 1])->count() > 0) {
                unset($data['authorize']);
            }
        }
        $data['authorize'] = array_values(array_filter(array_map('strval', (array)($data['authorize'] ?? []))));
    }

    /**
     * 站点超管不能被禁用或删除.
     */
    private function guardSuper(): void
    {
        $ids = str2arr(strval($this->request->post('id', '')));
        if (!empty($ids) && SiteUser::mk()->whereIn('id', $ids)->where(['is_super' => 1])->count() > 0) {
            $this->error('站点超级管理员不能被禁用或删除！');
        }
    }
}
