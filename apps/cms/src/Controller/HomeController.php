<?php

namespace App\Controller;

use App\DemoBlockBundle\Service\DemoBlockService;
use App\Service\GreetingService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private GreetingService $greetingService;

    private DemoBlockService $demoBlockService;

    private WorkspaceService $workspaceService;

    public function __construct(
        GreetingService $greetingService,
        DemoBlockService $demoBlockService,
        WorkspaceService $workspaceService
    ) {
        $this->greetingService = $greetingService;
        $this->demoBlockService = $demoBlockService;
        $this->workspaceService = $workspaceService;
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
            'storages' => $this->buildStoragesForView(),
        ]);
    }

    /**
     * @return list<array{id: string, slug: string, name: string, path: string, initials: string, location: string, avatar: string}>
     */
    private function buildStoragesForView(): array
    {
        $storages = [];
        foreach ($this->workspaceService->list() as $workspace) {
            $storages[] = [
                'id' => $workspace['id'],
                'slug' => $workspace['slug'],
                'name' => $workspace['name'],
                'path' => $workspace['folder'],
                'initials' => $this->workspaceService->initialsFromName($workspace['name']),
                'location' => $workspace['location'],
                'avatar' => $workspace['avatar'],
            ];
        }

        return $storages;
    }
}
