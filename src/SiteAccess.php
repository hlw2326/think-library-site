<?php

declare(strict_types=1);

namespace think\site;

use think\admin\facade\Library;
use think\admin\service\NodeService;
use think\site\model\SiteAuth;
use think\site\model\SiteAuthNode;
use think\site\model\SiteUser;

/**
 * 站点端登录态与节点授权.
 *
 * 会话键与总控后台的 user 键分开。每次请求都会重新读取用户并校验站点状态、账号状态，
 * 授权节点也实时从数据库读取，所以禁用账号、调整角色立即生效，不会等到重新登录。
 * @class SiteAccess
 */
final class SiteAccess
{
    private const SESSION = 'site_user';

    /**
     * 登录：写入会话.
     */
    public static function login(SiteUser $user, string $siteId): void
    {
        $session = Library::$sapp->session;
        // 防会话固定：登录成功更换会话 ID
        if (method_exists($session, 'regenerate')) {
            $session->regenerate();
        }
        $session->set(self::SESSION, ['id' => strval($user['id']), 'site_id' => $siteId]);
    }

    /**
     * 退出登录.
     */
    public static function logout(): void
    {
        Library::$sapp->session->delete(self::SESSION);
    }

    /**
     * 会话中保存的 [用户ID, 站点ID]，未登录返回 null.
     * @return null|array{0:string,1:string}
     */
    public static function session(): ?array
    {
        $data = Library::$sapp->session->get(self::SESSION);
        if (!is_array($data) || empty($data['id']) || empty($data['site_id'])) {
            return null;
        }
        return [strval($data['id']), strval($data['site_id'])];
    }

    /**
     * 恢复本次请求的登录态：绑定站点并读取用户.
     * 站点被停用/到期/删除、账号被禁用/删除，都视为未登录.
     * @return null|array 用户数据(含 nodes)，无效返回 null
     */
    public static function resume(): ?array
    {
        $session = static::session();
        if ($session === null) {
            return null;
        }
        [$userId, $siteId] = $session;
        $site = SiteResolver::byId($siteId);
        if (SiteResolver::reason($site) !== '') {
            return null;
        }
        SiteResolver::bind($site);
        $user = SiteUser::mk()->where(['id' => $userId, 'is_deleted' => 0])->findOrEmpty();
        if ($user->isEmpty() || intval($user['status']) !== 1) {
            return null;
        }
        $data = $user->toArray();
        unset($data['password']);
        $data['nodes'] = static::nodes($user);
        return $data;
    }

    /**
     * 用户当前被授予的节点(实时读取，已启用角色的授权并集).
     * @return string[]
     */
    public static function nodes(SiteUser $user): array
    {
        $ids = $user['authorize'];
        if (empty($ids)) {
            return [];
        }
        // 只认已启用的角色：禁用角色后，持有该角色的用户立即失去对应授权
        $enabled = SiteAuth::mk()->whereIn('id', $ids)->where(['status' => 1])->column('id');
        if (empty($enabled)) {
            return [];
        }
        $rows = SiteAuthNode::mk()->whereIn('auth', $enabled)->column('node');
        return array_values(array_unique(array_map('strval', $rows)));
    }

    /**
     * 节点授权检查：用户数据来自 resume().
     */
    public static function check(array $user, string $node): bool
    {
        if (!empty($user['is_super'])) {
            return true;
        }
        $method = NodeService::getMethods()[$node] ?? [];
        // 未标 @auth 的节点只要求登录
        if (empty($method['isauth'])) {
            return true;
        }
        return in_array($node, $user['nodes'] ?? [], true);
    }
}
