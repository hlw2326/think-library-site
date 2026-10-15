<?php

declare(strict_types=1);

namespace think\site\controller\manage;

use think\admin\base\Controller;
use think\admin\helper\QueryHelper;
use think\site\model\Site;
use think\site\model\SiteDomain;

/**
 * 站点域名管理(总控侧，走总控 RBAC).
 * @class Domain
 */
class Domain extends Controller
{
    /**
     * 域名列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        $this->type = strval($this->get['type'] ?? 'index');
        SiteDomain::mQuery()->layTable(function () {
            $this->title = '站点域名管理';
            $this->totalDomains = SiteDomain::mk()->count();
            $this->activeDomains = SiteDomain::mk()->where(['status' => 1])->count();
            $this->disabledDomains = SiteDomain::mk()->where(['status' => 0])->count();
            $this->sites = Site::mk()->where(['is_deleted' => 0])->column('name,code', 'id');
        }, function (QueryHelper $query) {
            if ($this->type === 'active') {
                $query->where(['status' => 1]);
            } elseif ($this->type === 'disabled') {
                $query->where(['status' => 0]);
            }
            $query->with(['site']);
            $query->like('domain,remark');
            $query->equal('status,site_id');
            $query->dateBetween('create_at');
        });
    }

    /**
     * 添加域名.
     * @auth true
     */
    public function add()
    {
        $this->title = '添加站点域名';
        SiteDomain::mForm('form');
    }

    /**
     * 编辑域名.
     * @auth true
     */
    public function edit()
    {
        $this->title = '编辑站点域名';
        SiteDomain::mForm('form');
    }

    /**
     * 修改域名状态.
     * @auth true
     */
    public function state()
    {
        SiteDomain::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除域名.
     * @auth true
     */
    public function remove()
    {
        SiteDomain::mDelete();
    }

    /**
     * 表单前置处理.
     */
    protected function _form_filter(array &$data)
    {
        if ($this->request->isGet()) {
            $this->sites = Site::mk()->where(['is_deleted' => 0])->order('sort desc,id asc')->column('name,code', 'id');
            return;
        }
        $domain = strtolower(trim(strval($data['domain'] ?? '')));
        $domain === '' && $this->error('绑定域名不能为空！');
        $domain = explode(':', explode('/', preg_replace('#^https?://#i', '', $domain))[0])[0];
        preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $domain) || $this->error('域名格式不正确！');
        $data['domain'] = $domain;
        $siteId = strval($data['site_id'] ?? '');
        $siteId === '' && $this->error('请选择所属站点！');
        Site::mk()->where(['id' => $siteId, 'is_deleted' => 0])->count() > 0 || $this->error('所属站点不存在！');

        // 域名全局唯一：已被任何站点(含自身其他记录)绑定都不允许重复
        $id = strval($data['id'] ?? '');
        $map = [['domain', '=', $domain]];
        $id === '' || $map[] = ['id', '<>', $id];
        SiteDomain::mk()->where($map)->count() > 0 && $this->error("域名【{$domain}】已被绑定，请更换！");
    }
}
