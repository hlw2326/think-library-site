<?php

declare(strict_types=1);

namespace think\site\model;

use think\site\Model;

/**
 * 站点角色(站点隔离).
 * @class SiteAuth
 * @property string $id 角色编号(UUID)
 * @property string $site_id 所属站点
 * @property string $title 角色名称
 * @property string $desc 备注说明
 * @property int $sort 排序权重
 * @property int $status 状态(1使用,0禁用)
 * @property string $create_at 创建时间
 */
class SiteAuth extends Model
{
    protected $createTime = 'create_at';

    protected $updateTime = false;

    /**
     * 日志名称.
     * @var string
     */
    protected $oplogName = '站点角色';

    /**
     * 日志类型.
     * @var string
     */
    protected $oplogType = '站点角色管理';
}
