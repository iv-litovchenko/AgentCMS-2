<?php

namespace App\DemoBlockBundle\Service;

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
                    'label' => 'Кубик из бандла',
                    'detail' => 'Данные из DemoBlockService',
                ],
                [
                    'label' => 'Twig partial',
                    'detail' => 'Resources/views/block.html.twig',
                ],
                [
                    'label' => 'Расширение',
                    'detail' => 'Новый блок без правок ядра',
                ],
            ],
        ];
    }
}
