<?php

namespace AgentCmsLight\Pages\DemoBlock;

use AgentCmsLight\Pages\DemoBlock\DependencyInjection\DemoBlockExtension;
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
