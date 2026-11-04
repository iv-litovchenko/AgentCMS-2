<?php

namespace App\Controller;

use App\Service\StarterEnvironmentService;
use App\Service\UiMenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    private UiMenuService $uiMenuService;

    private StarterEnvironmentService $starterEnvironmentService;

    public function __construct(UiMenuService $uiMenuService, StarterEnvironmentService $starterEnvironmentService)
    {
        $this->uiMenuService = $uiMenuService;
        $this->starterEnvironmentService = $starterEnvironmentService;
    }

    #[Route('/app/{name}/{section}', name: 'app_section', requirements: ['name' => '[a-z0-9_-]+', 'section' => '[a-z0-9_-]+'], methods: ['GET', 'POST'])]
    public function section(string $name, string $section, Request $request): Response
    {
        $app = $this->uiMenuService->findAppByName($name);
        if ($app === null) {
            throw $this->createNotFoundException('Приложение не найдено.');
        }

        $sectionMeta = $this->uiMenuService->findAppSection($name, $section);
        if ($sectionMeta === null) {
            throw $this->createNotFoundException('Раздел не найден.');
        }

        if ($name === 'documentation' && $section === 'typography') {
            return $this->forward('App\Controller\TypographyController::renderPage', [
                'request' => $request,
            ]);
        }

        $template = 'app/documentation/sections/' . $section . '.html.twig';
        if (!$this->container->get('twig')->getLoader()->exists($template)) {
            throw $this->createNotFoundException('Раздел не найден.');
        }

        return $this->render($template, [
            'app' => $app,
            'doc_app' => $app,
            'section' => $sectionMeta,
            'doc_sections' => $this->uiMenuService->getAppSections($name),
        ]);
    }

    #[Route('/app/{name}', name: 'app_show', requirements: ['name' => '[a-z0-9_-]+'], methods: ['GET'])]
    public function show(string $name): Response
    {
        $app = $this->uiMenuService->findAppByName($name);
        if ($app === null) {
            throw $this->createNotFoundException('Приложение не найдено.');
        }

        $defaultSection = $this->uiMenuService->getDefaultAppSectionSlug($name);
        if ($defaultSection !== null) {
            return $this->redirectToRoute('app_section', [
                'name' => $name,
                'section' => $defaultSection,
            ]);
        }

        if ($name === 'starter') {
            return $this->render('app/starter/index.html.twig', [
                'app' => $app,
                'environment' => $this->starterEnvironmentService->buildReport(),
            ]);
        }

        return $this->render('app/show.html.twig', [
            'app' => $app,
        ]);
    }
}
