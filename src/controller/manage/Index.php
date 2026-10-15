<?php

declare(strict_types=1);

namespace think\site\controller\manage;

use think\admin\base\Controller;
use think\admin\helper\QueryHelper;
use think\site\model\Site;
use think\site\model\SiteDomain;
use think\site\model\SiteUser;
use think\site\service\SiteContext;
use think\site\service\SiteInit;

/**
 * 站点管理(总控侧，走总控 RBAC).
 * @class Index
 */
class Index extends Controller
{
    /**
     * 站点列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->type = strval($this->get['type'] ?? 'index');
        Site::mQuery()->layTable(function () {
            $this->title = '站点列表管理';
            $expire = date('Y-m-d H:i:s', strtotime('+30 days'));
            $base = Site::mk()->where(['is_deleted' => 0]);
            $this->totalSites = (clone $base)->count();
            $this->activeSites = (clone $base)->where(['status' => 1])->count();
            $this->disabledSites = (clone $base)->where(['status' => 0])->count();
            $this->expireSites = (clone $base)->where('expire_at', '<', $expire)->count();
        }, function (QueryHelper $query) {
            $query->where(['is_deleted' => 0]);
            if ($this->type === 'active') {
                $query->where(['status' => 1]);
            } elseif ($this->type === 'disabled') {
                $query->where(['status' => 0]);
            } elseif ($this->type === 'expire') {
                $query->where('expire_at', '<', date('Y-m-d H:i:s', strtotime('+30 days')));
            }
            $query->like('name,code,contact,phone');
            $domain = trim(strval($this->request->get('domain', '')));
            if ($domain !== '') {
                $query->whereIn('id', SiteDomain::mk()->whereLike('domain', "%{$domain}%")->column('site_id'));
            }
            $query->equal('status');
            $query->dateBetween('expire_at,create_at');
        });
    }

    /**
     * 列表数据后置处理：附带绑定域名.
     */
    protected function _index_page_filter(array &$data)
    {
        $ids = array_column($data, 'id');
        $domains = [];
        if (!empty($ids)) {
            foreach (SiteDomain::mk()->whereIn('site_id', $ids)->where(['status' => 1])->select()->toArray() as $row) {
                $domains[$row['site_id']][] = $row['domain'];
            }
        }
        foreach ($data as &$vo) {
            $vo['scheme'] = $this->request->scheme();
            $vo['domains'] = implode(',', $domains[$vo['id']] ?? []);
        }
    }

    /**
     * 添加站点.
     * @auth true
     */
    public function add()
    {
        Site::mForm('form');
    }

    /**
     * 编辑站点.
     * @auth true
     */
    public function edit()
    {
        Site::mForm('form');
    }

    /**
     * 修改站点状态.
     * @auth true
     */
    public function state()
    {
        Site::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 站点扩展配置.
     * @auth true
     */
    public function config()
    {
        $site = $this->findSite();
        if ($this->request->isGet()) {
            $this->vo = $site->toArray();
            $this->fetch();
        }
        $site->save(['config' => (array)$this->request->post('config', [])]);
        $this->success('站点配置保存成功！');
    }

    /**
     * 重置站点超级管理员密码.
     * @auth true
     */
    public function pass()
    {
        $site = $this->findSite();
        $user = SiteContext::with(strval($site['id']), strval($site['code']), static function () {
            return SiteUser::mk()->where(['is_super' => 1, 'is_deleted' => 0])->order('create_at asc')->findOrEmpty();
        });
        $user->isEmpty() && $this->error('该站点没有超级管理员账号！');
        if ($this->request->isGet()) {
            $this->vo = ['id' => $site['id'], 'name' => $site['name'], 'username' => $user['username']];
            $this->fetch();
        }
        $data = $this->_vali([
            'password.require' => '登录密码不能为空！',
            'repassword.require' => '重复密码不能为空！',
            'repassword.confirm:password' => '两次输入的密码不一致！',
        ]);
        if (strlen($data['password']) < SiteInit::MIN_PASSWORD) {
            $this->error('登录密码不能少于' . SiteInit::MIN_PASSWORD . '位！');
        }
        SiteContext::with(strval($site['id']), strval($site['code']), static function () use ($user, $data) {
            SiteUser::mk()->where(['id' => $user['id']])->findOrEmpty()->save(['password' => password_hash($data['password'], PASSWORD_DEFAULT)]);
        });
        sysoplog('站点管理', "重置站点[{$site['name']}]超级管理员[{$user['username']}]密码");
        $this->success('密码修改成功！', '');
    }

    /**
     * 表单前置过滤.
     */
    protected function _form_filter(array &$data)
    {
        $id = strval($data['id'] ?? '');
        if (empty($data['expire_at'])) {
            $data['expire_at'] = date('Y-m-d 23:59:59', strtotime('+1 year'));
        }
        if (!$this->request->isPost()) {
            return;
        }
        empty($data['name']) && $this->error('站点名称不能为空！');
        $code = strtolower(trim(strval($data['code'] ?? '')));
        preg_match('/^[a-z0-9_-]{2,30}$/', $code) || $this->error('站点编码仅支持2-30位小写字母、数字、下划线及连字符！');
        $data['code'] = $code;
        $map = [['code', '=', $code], ['is_deleted', '=', 0]];
        $id === '' || $map[] = ['id', '<>', $id];
        Site::mk()->where($map)->count() > 0 && $this->error("站点编码【{$code}】已被使用，请更换！");
        if ($id === '') {
            trim(strval($data['admin_user'] ?? '')) === '' && $this->error('新建站点必须设置管理员账号！');
            strlen(strval($data['admin_pass'] ?? '')) < SiteInit::MIN_PASSWORD
            && $this->error('新建站点必须设置不少于' . SiteInit::MIN_PASSWORD . '位的管理员密码！');
        }
        if (!empty($data['config']) && is_array($data['config'])) {
            $data['config'] = json_encode($data['config'], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * 表单后置：新建站点后初始化默认角色与超管；初始化失败则撤销站点，避免留下没人能登录的站点.
     */
    protected function _form_result(bool $status, array $data)
    {
        if (!$status || !empty($data['id']) || empty($data['code'])) {
            return;
        }
        $site = Site::mk()->where(['code' => $data['code'], 'is_deleted' => 0])->findOrEmpty();
        if ($site->isEmpty()) {
            return;
        }
        try {
            SiteInit::init($site, strval($data['admin_user']), strval($data['admin_pass']));
        } catch (\Throwable $e) {
            $site->save(['is_deleted' => 1]);
            $this->error('站点初始化失败：' . $e->getMessage());
        }
    }

    /**
     * 按请求参数查找站点.
     */
    private function findSite(): Site
    {
        $id = strval($this->request->post('id', $this->request->get('id', '')));
        $site = Site::mk()->where(['id' => $id, 'is_deleted' => 0])->findOrEmpty();
        $site->isEmpty() && $this->error('站点不存在或已被删除！');
        return $site;
    }
}
