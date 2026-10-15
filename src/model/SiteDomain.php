<?php

declare(strict_types=1);

namespace think\site\model;

use think\model\relation\BelongsTo;
use think\site\GlobalModel;

/**
 * 站点域名.
 * @class SiteDomain
 * @property string $id 域名编号(UUID)
 * @property string $site_id 所属站点
 * @property string $domain 绑定域名
 * @property int $status 状态(1正常,0禁用)
 * @property string $remark 备注说明
 * @property string $create_at 创建时间
 * @property string $update_at 更新时间
 */
class SiteDomain extends GlobalModel
{
    /**
     * 日志名称.
     * @var string
     */
    protected $oplogName = '站点域名';

    /**
     * 日志类型.
     * @var string
     */
    protected $oplogType = '站点域名管理';

    /**
     * 关联所属站点.
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }
}
