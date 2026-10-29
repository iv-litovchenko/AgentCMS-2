<?php

namespace App\Controller;

use App\DemoBlockBundle\Service\DemoBlockService;
use App\Service\GreetingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private GreetingService $greetingService;

    private DemoBlockService $demoBlockService;

    public function __construct(GreetingService $greetingService, DemoBlockService $demoBlockService)
    {
        $this->greetingService = $greetingService;
        $this->demoBlockService = $demoBlockService;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->renderHome(false);
    }

    #[Route('/compact', name: 'home_compact')]
    public function compact(): Response
    {
        return $this->renderHome(true);
    }

    private function renderHome(bool $compact): Response
    {
        return $this->render('home/index.html.twig', [
            'compact' => $compact,
            'title' => 'Agent CMS Light',
            'greeting' => $this->greetingService->getMessage(),
            'demo_block' => $this->demoBlockService->getBlockData(),
            'storages' => $this->getStorages(),
        ]);
    }

    /**
     * @return list<array{name: string, path: string, initials: string, active: bool}>
     */
    private function getStorages(): array
    {
        return [
            [
                'name' => 'Основной workspace',
                'path' => 'storage/app/workspaces/demo-main',
                'initials' => 'OW',
                'active' => true,
            ],
            [
                'name' => 'Песочница',
                'path' => 'storage/app/workspaces/demo-sandbox',
                'initials' => 'П',
                'active' => true,
            ],
            [
                'name' => 'Архив 2025',
                'path' => 'storage/app/workspaces/archive-2025',
                'initials' => 'A',
                'active' => false,
            ],
        ];
    }
}
