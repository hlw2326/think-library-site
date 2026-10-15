<?php

declare(strict_types=1);

namespace think\site\model;

use think\model\relation\HasMany;
use think\site\GlobalModel;

/**
 * 站点.
 * @class Site
 * @property string $id 站点编号(UUID)
 * @property string $name 站点名称
 * @property string $code 站点编码
 * @property string $logo 站点Logo
 * @property string $contact 联系人
 * @property string $phone 联系电话
 * @property string $email 联系邮箱
 * @property string $address 详细地址
 * @property int $status 状态(1正常,0禁用)
 * @property string $expire_at 站点有效期
 * @property int $sort 排序权重
 * @property string $remark 备注说明
 * @property array|string $config 扩展配置
 * @property int $is_deleted 软删除(0未删,1已删)
 * @property string $create_at 创建时间
 * @property string $update_at 更新时间
 */
class Site extends GlobalModel
{
    /**
     * 日志名称.
     * @var string
     */
    protected $oplogName = '站点';

    /**
     * 日志类型.
     * @var string
     */
    protected $oplogType = '站点管理';

    /**
     * 关联多域名.
     */
    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class, 'site_id', 'id');
    }

    /**
     * 读取扩展配置.
     * @param mixed $value
     */
    public function getConfigAttr($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode(strval($value), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 写入扩展配置.
     * @param mixed $value
     */
    public function setConfigAttr($value): string
    {
        return is_string($value) ? $value : json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 站点当前是否可用：已启用、未删除、未到期.
     */
    public function isAvailable(): bool
    {
        if (intval($this->getAttr('status')) !== 1 || intval($this->getAttr('is_deleted')) !== 0) {
            return false;
        }
        $expire = strval($this->getAttr('expire_at') ?? '');
        return $expire === '' || strtotime($expire) === false || strtotime($expire) > time();
    }
}
