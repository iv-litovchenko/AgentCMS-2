<?php

namespace AgentCms\Pages\DemoBlock\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DemoBlockExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator($this->getPath() . '/config'));
        $loader->load('services.yaml');
    }

    private function getPath(): string
    {
        return \dirname(__DIR__, 2);
    }
}
