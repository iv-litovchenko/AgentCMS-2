<?php

namespace AgentCms\Pages\DemoBlock;

use AgentCms\Pages\DemoBlock\DependencyInjection\DemoBlockExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DemoBlockBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtensionClass(): string
    {
        return DemoBlockExtension::class;
    }
}
