<?php

declare(strict_types=1);

namespace think\site\model;

use think\site\base\Model;

/**
 * 站点配置(站点隔离).
 * @class SiteConfig
 * @property string $id 编号(UUID)
 * @property string $site_id 所属站点
 * @property string $type 配置分类
 * @property string $name 配置名称
 * @property string $value 配置内容
 */
class SiteConfig extends Model
{
    protected $autoWriteTimestamp = false;
}
