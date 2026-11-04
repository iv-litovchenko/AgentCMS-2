<?php

namespace AgentCmsLight\Pages\DemoBlock;

class DemoBlockService
{
    /**
     * @return array{title: string, items: list<array{label: string, detail: string}>}
     */
    public function getBlockData(): array
    {
        return [
            'title' => 'Demo Block',
            'items' => [
                [
                    'label' => 'Пакет packages/demo-block',
                    'detail' => 'agent-cms-light/page-demo-block через Composer',
                ],
                [
                    'label' => 'Twig partial',
                    'detail' => '@DemoBlock/block.html.twig',
                ],
                [
                    'label' => 'Расширение',
                    'detail' => 'Новый блок без правок ядра CMS',
                ],
            ],
        ];
    }
}
