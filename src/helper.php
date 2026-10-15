<?php

declare(strict_types=1);

use think\site\model\SiteConfig;
use think\site\service\SiteContext;

if (!function_exists('siteid')) {
    /**
     * 当前站点 ID，未绑定为空字符串.
     */
    function siteid(): string
    {
        return SiteContext::id();
    }
}

if (!function_exists('sitecode')) {
    /**
     * 当前站点代码，未绑定为空字符串.
     */
    function sitecode(): string
    {
        return SiteContext::code();
    }
}

if (!function_exists('siteconf')) {
    /**
     * 读写当前站点配置，名称格式 "分类.名称"；传入 $value 为写入，否则读取.
     * @param mixed $value
     * @return mixed
     */
    function siteconf(string $name, $value = null, $default = '')
    {
        [$type, $field] = str_contains($name, '.') ? explode('.', $name, 2) : ['base', $name];
        $query = SiteConfig::mk()->where(['type' => $type, 'name' => $field]);
        if ($value === null) {
            $row = $query->findOrEmpty();
            return $row->isEmpty() ? $default : $row['value'];
        }
        $row = $query->findOrEmpty();
        return $row->save(['type' => $type, 'name' => $field, 'value' => strval($value)]);
    }
}
