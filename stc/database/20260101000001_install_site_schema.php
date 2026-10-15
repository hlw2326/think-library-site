<?php

declare(strict_types=1);

use think\admin\database\migration\PhinxExtend;
use think\migration\Migrator;
use think\site\Service;
use think\site\phinx\PhinxExtendV2;

@set_time_limit(0);
@ini_set('memory_limit', '-1');

/**
 * 站点库数据表：站点、域名为全局表，用户/角色/授权/配置为站点隔离表.
 */
class InstallSiteSchema extends Migrator
{
    /**
     * 获取脚本名称.
     */
    public function getName(): string
    {
        return 'SitePlugin';
    }

    /**
     * 创建数据库.
     */
    public function change()
    {
        $this->_create_site();
        $this->_create_site_domain();
        $this->_create_site_user();
        $this->_create_site_auth();
        $this->_create_site_auth_node();
        $this->_create_site_config();
        $this->insertMenu();
    }

    /**
     * 创建数据对象.
     * @class Site
     * @table site
     */
    private function _create_site(): void
    {
        $table = $this->table('site', ['comment' => '站点']);
        PhinxExtendV2::global($table, [
            ['name', 'string', ['limit' => 100, 'default' => '', 'null' => false, 'comment' => '站点名称']],
            ['code', 'string', ['limit' => 50, 'default' => '', 'null' => false, 'comment' => '站点编码']],
            ['logo', 'string', ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '站点Logo']],
            ['contact', 'string', ['limit' => 50, 'default' => '', 'null' => true, 'comment' => '联系人']],
            ['phone', 'string', ['limit' => 20, 'default' => '', 'null' => true, 'comment' => '联系电话']],
            ['email', 'string', ['limit' => 50, 'default' => '', 'null' => true, 'comment' => '联系邮箱']],
            ['address', 'string', ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '详细地址']],
            ['status', 'integer', ['limit' => 1, 'default' => 1, 'null' => true, 'comment' => '状态(1正常,0禁用)']],
            ['expire_at', 'string', ['limit' => 20, 'default' => null, 'null' => true, 'comment' => '站点有效期']],
            ['sort', 'biginteger', ['limit' => 20, 'default' => 0, 'null' => true, 'comment' => '排序权重']],
            ['remark', 'string', ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '备注说明']],
            ['config', 'text', ['default' => null, 'null' => true, 'comment' => '扩展配置']],
            ['is_deleted', 'integer', ['limit' => 1, 'default' => 0, 'null' => true, 'comment' => '软删除(0未删,1已删)']],
            ['create_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true, 'comment' => '创建时间']],
            ['update_at', 'timestamp', ['default' => null, 'null' => true, 'comment' => '更新时间']],
        ], [
            ['columns' => ['code'], 'unique' => true], 'status', 'is_deleted', 'sort',
        ], true);
    }

    /**
     * 创建数据对象.
     * @class SiteDomain
     * @table site_domain
     */
    private function _create_site_domain(): void
    {
        $table = $this->table('site_domain', ['comment' => '站点域名']);
        PhinxExtendV2::global($table, [
            PhinxExtendV2::ref('site_id', false, '所属站点'),
            ['domain', 'string', ['limit' => 100, 'default' => '', 'null' => false, 'comment' => '绑定域名']],
            ['status', 'integer', ['limit' => 1, 'default' => 1, 'null' => true, 'comment' => '状态(1正常,0禁用)']],
            ['remark', 'string', ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '备注说明']],
            ['create_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true, 'comment' => '创建时间']],
            ['update_at', 'timestamp', ['default' => null, 'null' => true, 'comment' => '更新时间']],
        ], [
            'site_id', ['columns' => ['domain'], 'unique' => true], 'status',
        ], true);
    }

    /**
     * 创建数据对象.
     * @class SiteUser
     * @table site_user
     */
    private function _create_site_user(): void
    {
        $table = $this->table('site_user', ['comment' => '站点用户']);
        PhinxExtendV2::upgrade($table, [
            ['username', 'string', ['limit' => 50, 'default' => '', 'null' => false, 'comment' => '登录账号']],
            ['password', 'string', ['limit' => 255, 'default' => '', 'null' => false, 'comment' => '登录密码(password_hash)']],
            ['nickname', 'string', ['limit' => 50, 'default' => '', 'null' => true, 'comment' => '用户昵称']],
            ['headimg', 'string', ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '头像地址']],
            ['authorize', 'string', ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '角色授权(角色ID逗号分隔)']],
            ['contact_phone', 'string', ['limit' => 20, 'default' => '', 'null' => true, 'comment' => '联系手机']],
            ['contact_mail', 'string', ['limit' => 50, 'default' => '', 'null' => true, 'comment' => '联系邮箱']],
            ['login_ip', 'string', ['limit' => 255, 'default' => '', 'null' => true, 'comment' => '登录地址']],
            ['login_at', 'string', ['limit' => 20, 'default' => '', 'null' => true, 'comment' => '登录时间']],
            ['login_num', 'biginteger', ['limit' => 20, 'default' => 0, 'null' => true, 'comment' => '登录次数']],
            ['sort', 'biginteger', ['limit' => 20, 'default' => 0, 'null' => true, 'comment' => '排序权重']],
            ['status', 'integer', ['limit' => 1, 'default' => 1, 'null' => true, 'comment' => '状态(0禁用,1启用)']],
            ['is_super', 'integer', ['limit' => 1, 'default' => 0, 'null' => true, 'comment' => '站点超管(1是,0否)']],
            ['is_deleted', 'integer', ['limit' => 1, 'default' => 0, 'null' => true, 'comment' => '删除(1删除,0未删)']],
            ['create_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true, 'comment' => '创建时间']],
        ], [
            ['columns' => ['site_id', 'username'], 'unique' => true], 'status', 'is_deleted',
        ], true);
    }

    /**
     * 创建数据对象.
     * @class SiteAuth
     * @table site_auth
     */
    private function _create_site_auth(): void
    {
        $table = $this->table('site_auth', ['comment' => '站点角色']);
        PhinxExtendV2::upgrade($table, [
            ['title', 'string', ['limit' => 100, 'default' => '', 'null' => false, 'comment' => '角色名称']],
            ['desc', 'string', ['limit' => 500, 'default' => '', 'null' => true, 'comment' => '备注说明']],
            ['sort', 'biginteger', ['limit' => 20, 'default' => 0, 'null' => true, 'comment' => '排序权重']],
            ['status', 'integer', ['limit' => 1, 'default' => 1, 'null' => true, 'comment' => '状态(1使用,0禁用)']],
            ['create_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'null' => true, 'comment' => '创建时间']],
        ], [
            'status', 'sort',
        ], true);
    }

    /**
     * 创建数据对象.
     * @class SiteAuthNode
     * @table site_auth_node
     */
    private function _create_site_auth_node(): void
    {
        $table = $this->table('site_auth_node', ['comment' => '站点授权']);
        PhinxExtendV2::upgrade($table, [
            PhinxExtendV2::ref('auth', false, '角色ID'),
            ['node', 'string', ['limit' => 200, 'default' => '', 'null' => true, 'comment' => '节点']],
        ], [
            'auth', 'node',
        ], true);
    }

    /**
     * 创建数据对象.
     * @class SiteConfig
     * @table site_config
     */
    private function _create_site_config(): void
    {
        $table = $this->table('site_config', ['comment' => '站点配置']);
        PhinxExtendV2::upgrade($table, [
            ['type', 'string', ['limit' => 20, 'default' => '', 'null' => true, 'comment' => '配置分类']],
            ['name', 'string', ['limit' => 100, 'default' => '', 'null' => true, 'comment' => '配置名称']],
            ['value', 'string', ['limit' => 2048, 'default' => '', 'null' => true, 'comment' => '配置内容']],
        ], [
            ['columns' => ['site_id', 'type', 'name'], 'unique' => true],
        ], true);
    }

    private function insertMenu(): void
    {
        $row = $this->fetchRow("SELECT 1 FROM `system_menu` WHERE `url` = 'site/manage.index/index' OR `node` = 'site/manage.index/index' LIMIT 1");
        if ($row) {
            return;
        }
        PhinxExtend::write2menu(Service::menu(), [], $this->table('system_menu'));
    }
}
