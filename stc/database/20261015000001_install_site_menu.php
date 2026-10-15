<?php

declare(strict_types=1);

use think\admin\database\migration\PhinxExtend;
use think\migration\Migrator;
use think\site\Service;

class InstallSiteMenu extends Migrator
{
    public function getName(): string
    {
        return 'SitePlugin';
    }

    public function change(): void
    {
        $row = $this->fetchRow("SELECT 1 FROM `system_menu` WHERE `url` = 'site/manage.index/index' OR `node` = 'site/manage.index/index' LIMIT 1");
        if ($row) {
            return;
        }
        PhinxExtend::write2menu(Service::menu(), [], $this->table('system_menu'));
    }
}
