<?php

declare(strict_types=1);

namespace think\site;

use think\admin\extend\CodeExtend;

/**
 * 全局模型基类：不属于任何站点的表(例如站点表本身)，不做站点隔离，但同样使用 uuid 主键.
 * @class GlobalModel
 */
abstract class GlobalModel extends \think\admin\base\Model
{
    /**
     * 不启用任何全局查询范围.
     * @var array
     */
    protected $globalScope = [];

    /**
     * 自动时间戳.
     * @var string
     */
    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';

    /**
     * 新增前生成 uuid 主键(MySQL / SQLite 没有服务端 uuidv7).
     * @param mixed $model
     */
    public static function onBeforeInsert($model): void
    {
        $id = $model->getAttr('id');
        if ($id === null || $id === '') {
            $model->setAttr('id', CodeExtend::uuidv7());
        }
    }
}
