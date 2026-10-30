<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FlowbiteDemoController extends AbstractController
{
    #[Route('/flowbite-demo', name: 'flowbite_demo', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('demo/flowbite/index.html.twig');
    }
}
