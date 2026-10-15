<?php

declare(strict_types=1);

namespace think\site\model;

use think\site\Model;

/**
 * 站点角色授权节点(站点隔离).
 * @class SiteAuthNode
 * @property string $id 编号(UUID)
 * @property string $site_id 所属站点
 * @property string $auth 角色编号
 * @property string $node 节点
 */
class SiteAuthNode extends Model
{
    protected $autoWriteTimestamp = false;
}
