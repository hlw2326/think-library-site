<?php

declare(strict_types=1);

use think\admin\database\migration\PhinxExtend;
use think\migration\Migrator;
use think\site\Service;

class UpdateSiteConsoleMenu extends Migrator
{
    public function getName(): string
    {
        return 'SitePlugin';
    }

    public function change(): void
    {
        $row = $this->fetchRow("SELECT 1 FROM `system_menu` WHERE `url` = 'site/console.index/index' OR `node` = 'site/console.index/index' LIMIT 1");
        if ($row) {
            return;
        }
        $menus = Service::menu();
        PhinxExtend::write2menu([$menus[1]], [], $this->table('system_menu'));
    }
}
