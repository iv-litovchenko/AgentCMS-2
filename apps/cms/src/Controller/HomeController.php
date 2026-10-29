<?php

namespace App\Controller;

use App\Service\GreetingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private GreetingService $greetingService;

    public function __construct(GreetingService $greetingService)
    {
        $this->greetingService = $greetingService;
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'title' => 'Agent CMS Light',
            'greeting' => $this->greetingService->getMessage(),
            'storages' => [
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
            ],
        ]);
    }
}
