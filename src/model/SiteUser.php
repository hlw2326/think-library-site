<?php

declare(strict_types=1);

namespace think\site\model;

use think\site\Model;

/**
 * 站点用户(站点隔离).
 * @class SiteUser
 * @property string $id 用户编号(UUID)
 * @property string $site_id 所属站点
 * @property string $username 登录账号
 * @property string $password 登录密码(password_hash)
 * @property string $nickname 用户昵称
 * @property string $headimg 头像地址
 * @property string $authorize 角色授权(角色ID逗号分隔)
 * @property string $contact_phone 联系手机
 * @property string $contact_mail 联系邮箱
 * @property string $login_ip 登录地址
 * @property string $login_at 登录时间
 * @property int $login_num 登录次数
 * @property int $sort 排序权重
 * @property int $status 状态(0禁用,1启用)
 * @property int $is_super 站点超管(1是,0否)
 * @property int $is_deleted 删除(1删除,0未删)
 * @property string $create_at 创建时间
 */
class SiteUser extends Model
{
    protected $createTime = 'create_at';

    protected $updateTime = false;

    /**
     * 序列化输出(列表接口、表单回显)时隐藏密码哈希；属性访问 $user['password'] 不受影响.
     * @var array
     */
    protected $hidden = ['password'];

    /**
     * 日志名称.
     * @var string
     */
    protected $oplogName = '站点用户';

    /**
     * 日志类型.
     * @var string
     */
    protected $oplogType = '站点用户管理';

    /**
     * 读取角色授权.
     * @param mixed $value
     */
    public function getAuthorizeAttr($value): array
    {
        return is_array($value) ? $value : str2arr(strval($value));
    }

    /**
     * 写入角色授权.
     * @param mixed $value
     */
    public function setAuthorizeAttr($value): string
    {
        return is_array($value) ? arr2str($value) : strval($value);
    }
}
