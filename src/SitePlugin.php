<?php

declare(strict_types=1);

namespace think\site;

use think\admin\facade\Plugin;

/**
 * 站点插件基类.
 *
 * 继承它即声明"这是分站插件"：SiteRegistry 靠类继承关系识别，而不是靠 composer.json 里的标记，
 * 标记可能和代码不一致，类继承不会。分站插件的控制器应继承 think\site\Controller，
 * 模型应继承 think\site\Model，迁移应使用 PhinxExtendV2。
 * @class SitePlugin
 */
abstract class SitePlugin extends Plugin {}
