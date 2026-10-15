<?php

declare(strict_types=1);

namespace think\site\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\site\Model;
use think\site\SitePlugin;
use think\site\SiteRegistry;

/**
 * 站点一致性自检：分站插件的声明、依赖与表结构是否对得上.
 * @class SiteCheck
 */
class SiteCheck extends Command
{
    protected function configure()
    {
        $this->setName('site:check');
        $this->setDescription('Check site plugins: SitePlugin inheritance, composer require and site_id columns');
    }

    protected function execute(Input $input, Output $output)
    {
        $errors = 0;
        $sites = SiteRegistry::all();
        $output->writeln('分站插件：' . (empty($sites) ? '(无)' : implode(', ', array_keys($sites))));

        // 1/2：composer require 与类继承是否一致
        foreach (\think\admin\facade\Plugin::get() ?? [] as $code => $addon) {
            $package = strval($addon['package'] ?? '');
            $service = strval($addon['service'] ?? '');
            if ($package === '' || $package === 'hlw2326/think-site-library') {
                continue;
            }
            $requires = $this->requires($package);
            $isSite = isset($sites[$code]);
            $dependsOnSite = in_array('hlw2326/think-site-library', $requires, true);
            if ($dependsOnSite && !$isSite) {
                $output->error("插件 {$code}({$package}) 依赖 think-site-library，但服务类 {$service} 没有继承 " . SitePlugin::class);
                ++$errors;
            }
            if ($isSite && !$dependsOnSite) {
                $output->error("插件 {$code}({$package}) 继承了 SitePlugin，但 composer.json 没有 require hlw2326/think-site-library");
                ++$errors;
            }
        }

        // 3：分站插件里继承 think\site\Model 的模型，对应表必须有 site_id
        foreach ($sites as $code => $addon) {
            foreach (glob(rtrim(strval($addon['path']), '/\\') . '/model/*.php') ?: [] as $file) {
                $class = $addon['space'] . '\\model\\' . basename($file, '.php');
                if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                    continue;
                }
                try {
                    $fields = (new $class())->getTableFields();
                } catch (\Throwable $e) {
                    $output->error("模型 {$class} 读取表结构失败：" . $e->getMessage());
                    ++$errors;
                    continue;
                }
                if (!in_array('site_id', $fields, true)) {
                    $output->error("模型 {$class} 继承了 think\\site\\Model，但表缺少 site_id 列，请用 PhinxExtendV2 建表");
                    ++$errors;
                }
            }
        }

        $output->writeln($errors === 0 ? '<info>site:check 通过</info>' : "<error>site:check 发现 {$errors} 个问题</error>");
        return $errors === 0 ? 0 : 1;
    }

    /**
     * 读取已安装包的 require 列表.
     * @return string[]
     */
    private function requires(string $package): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            $file = syspath('vendor/composer/installed.json');
            $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
            foreach ($data['packages'] ?? [] as $item) {
                $map[$item['name']] = array_keys($item['require'] ?? []);
            }
        }
        return $map[$package] ?? [];
    }
}
