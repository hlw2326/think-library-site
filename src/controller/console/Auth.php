<?php

declare(strict_types=1);

namespace think\site\controller\console;

use think\admin\helper\QueryHelper;
use think\admin\service\NodeService;
use think\site\Controller;
use think\site\model\SiteAuth;
use think\site\model\SiteAuthNode;
use think\site\SiteRegistry;

/**
 * 站点角色管理.
 * @class Auth
 */
class Auth extends Controller
{
    /**
     * 站点角色列表.
     * @auth true
     * @menu true
     */
    public function index()
    {
        SiteAuth::mQuery()->layTable(function () {
            $this->title = '站点角色管理';
        }, function (QueryHelper $query) {
            $query->like('title')->equal('status');
        });
    }

    /**
     * 添加站点角色.
     * @auth true
     */
    public function add()
    {
        SiteAuth::mForm('form');
    }

    /**
     * 编辑站点角色.
     * @auth true
     */
    public function edit()
    {
        SiteAuth::mForm('form');
    }

    /**
     * 修改角色状态.
     * @auth true
     */
    public function state()
    {
        SiteAuth::mSave($this->_vali([
            'status.in:0,1' => '状态值范围异常！',
            'status.require' => '状态值不能为空！',
        ]));
    }

    /**
     * 删除站点角色.
     * @auth true
     */
    public function remove()
    {
        $ids = str2arr(strval($this->request->post('id', '')));
        empty($ids) && $this->error('角色标识不能为空！');
        SiteAuthNode::mk()->whereIn('auth', $ids)->delete();
        SiteAuth::mk()->whereIn('id', $ids)->delete();
        $this->success('删除成功！');
    }

    /**
     * 角色授权：可授权节点按应用分组，只列出站点控制台和分站插件的节点.
     * @auth true
     */
    public function nodes()
    {
        $id = strval($this->request->post('id', $this->request->get('id', '')));
        $auth = SiteAuth::mk()->where(['id' => $id])->findOrEmpty();
        $auth->isEmpty() && $this->error('角色不存在或无权操作！');
        if ($this->request->isGet()) {
            $this->vo = $auth->toArray();
            $this->groups = $this->groups();
            $this->checked = SiteAuthNode::mk()->where(['auth' => $id])->column('node');
            $this->fetch();
        }
        // 只接受确实存在且需要授权的节点，其余提交值直接丢弃
        $allow = array_merge(...array_map('array_keys', array_values($this->groups())) ?: [[]]);
        $nodes = array_values(array_unique(array_intersect(array_map('strval', (array)$this->request->post('nodes', [])), $allow)));
        $this->app->db->transaction(function () use ($id, $nodes) {
            SiteAuthNode::mk()->where(['auth' => $id])->delete();
            foreach ($nodes as $node) {
                SiteAuthNode::mk()->save(['auth' => $id, 'node' => $node]);
            }
        });
        $this->success('角色授权成功！', 'javascript:history.back()');
    }

    /**
     * 可授权节点：[应用编码 => [节点 => 标题]].
     * @return array<string, array<string, string>>
     */
    private function groups(): array
    {
        $apps = array_merge(['site'], array_keys(SiteRegistry::all()));
        $groups = [];
        foreach (NodeService::getMethods() as $node => $method) {
            $app = explode('/', $node)[0] ?? '';
            if (!in_array($app, $apps, true) || empty($method['isauth'])) {
                continue;
            }
            // 站点应用里只有 console.* 属于站点端，manage.* 是总控管理
            if ($app === 'site' && !str_starts_with($node, 'site/console.')) {
                continue;
            }
            $groups[$app][$node] = lang(strval($method['title'] ?? $node));
        }
        return $groups;
    }
}
