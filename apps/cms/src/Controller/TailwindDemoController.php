<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TailwindDemoController extends AbstractController
{
    #[Route('/tailwind-demo', name: 'tailwind_demo', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('tailwind_demo/index.html.twig', [
            'compact' => $request->query->getBoolean('compact'),
        ]);
    }
}
