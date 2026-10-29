<?php

namespace App\Controller;

use App\Service\GreetingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HelloApiController extends AbstractController
{
    private GreetingService $greetingService;

    public function __construct(GreetingService $greetingService)
    {
        $this->greetingService = $greetingService;
    }

    #[Route('/api/hello', name: 'api_hello', methods: ['GET'])]
    public function hello(): JsonResponse
    {
        return $this->json([
            'message' => $this->greetingService->getMessage(),
        ]);
    }
}
