<?php

declare(strict_types=1);

namespace think\site\base;

use think\admin\extend\CodeExtend;
use think\db\Query;
use think\site\service\SiteContext;

/**
 * 站点隔离模型基类.
 *
 * 继承它的模型：
 *  - 查询自动加 site_id 条件；未绑定站点、或表缺少 site_id 列时直接抛异常(不放行)；
 *  - 新增自动补 site_id，并在插入前生成 uuid 主键；
 *  - 禁止修改已有记录的 site_id。
 *
 * 局限：只有走模型的读写受保护，Db::table() 之类的原生写入不会触发这些逻辑。
 * 需要跨站点查询时显式 withoutGlobalScope()。
 * @class Model
 */
abstract class Model extends \think\admin\base\Model
{
    /**
     * 全局查询范围.
     * @var array
     */
    protected $globalScope = ['site'];

    /**
     * 拦截批量/裸写入，补齐 site_id 与 uuid 主键.
     * @param string $method 方法名称
     * @param array $args 调用参数
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (in_array($method, ['insert', 'insertGetId', 'insertAll'], true) && is_array($args[0] ?? null)) {
            $site = SiteContext::require();
            $rows = $method === 'insertAll' ? $args[0] : [$args[0]];
            $ids = [];
            foreach ($rows as &$row) {
                if (is_array($row)) {
                    $row['site_id'] = $site;
                    empty($row['id']) && $row['id'] = CodeExtend::uuidv7();
                    $ids[] = $row['id'];
                }
            }
            unset($row);
            $args[0] = $method === 'insertAll' ? $rows : $rows[0];
            $result = parent::__call($method, $args);
            // uuid 主键没有自增序列，insertGetId 直接返回我们生成的 id
            return $method === 'insertGetId' ? $ids[0] : $result;
        }
        return parent::__call($method, $args);
    }

    /**
     * 站点隔离全局作用域.
     */
    public function scopeSite(Query $query): void
    {
        $site = SiteContext::require();
        $fields = $this->getTableFields();
        if (!in_array('site_id', $fields, true)) {
            throw new \RuntimeException(sprintf('表 %s 没有 site_id 列，无法做站点隔离，请用 PhinxExtendV2 建表！', $this->getTable()));
        }
        $table = $query->getTable();
        $alias = $query->getOptions('alias');
        $target = $table;
        if (is_array($alias) && !empty($alias[$table])) {
            $target = $alias[$table];
        } elseif (is_string($alias) && $alias !== '') {
            $target = $alias;
        }
        $query->where("{$target}.site_id", $site);
    }

    /**
     * 新增前补齐 site_id 与 uuid 主键.
     * @param mixed $model
     */
    public static function onBeforeInsert($model): void
    {
        $model->setAttr('site_id', SiteContext::require());
        $id = $model->getAttr('id');
        if ($id === null || $id === '') {
            $model->setAttr('id', CodeExtend::uuidv7());
        }
    }

    /**
     * 更新前禁止修改 site_id.
     * @param mixed $model
     */
    public static function onBeforeUpdate($model): void
    {
        $origin = $model->getOrigin('site_id');
        if ($origin !== null && strval($model->getData('site_id')) !== strval($origin)) {
            throw new \RuntimeException('禁止修改记录所属站点！');
        }
    }
}
