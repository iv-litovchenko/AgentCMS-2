<?php

namespace App\Controller;

use App\Service\ProjectReadmeService;
use App\Service\StarterEnvironmentService;
use App\Service\StarterLogJournalService;
use App\Service\StarterToggleService;
use App\Service\UiMenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    private UiMenuService $uiMenuService;

    private StarterEnvironmentService $starterEnvironmentService;

    private StarterToggleService $starterToggleService;

    private StarterLogJournalService $starterLogJournalService;

    private ProjectReadmeService $projectReadmeService;

    public function __construct(
        UiMenuService $uiMenuService,
        StarterEnvironmentService $starterEnvironmentService,
        StarterToggleService $starterToggleService,
        StarterLogJournalService $starterLogJournalService,
        ProjectReadmeService $projectReadmeService
    ) {
        $this->uiMenuService = $uiMenuService;
        $this->starterEnvironmentService = $starterEnvironmentService;
        $this->starterToggleService = $starterToggleService;
        $this->starterLogJournalService = $starterLogJournalService;
        $this->projectReadmeService = $projectReadmeService;
    }

    #[Route('/app/{name}/{section}', name: 'app_section', requirements: ['name' => '[a-z0-9_-]+', 'section' => '[a-z0-9_-]+'], methods: ['GET', 'POST'])]
    public function section(string $name, string $section, Request $request): Response
    {
        if ($name === 'documentation' && $section === 'typography') {
            $params = ['name' => 'typography'];
            if ($request->query->getBoolean('compact')) {
                $params['compact'] = 1;
            }

            return $this->redirectToRoute('app_show', $params, Response::HTTP_MOVED_PERMANENTLY);
        }

        $app = $this->uiMenuService->findAppByName($name);
        if ($app === null) {
            throw $this->createNotFoundException('Приложение не найдено.');
        }

        $sectionMeta = $this->uiMenuService->findAppSection($name, $section);
        if ($sectionMeta === null) {
            throw $this->createNotFoundException('Раздел не найден.');
        }

        $template = 'app/documentation/sections/' . $section . '.html.twig';
        if (!$this->container->get('twig')->getLoader()->exists($template)) {
            throw $this->createNotFoundException('Раздел не найден.');
        }

        $view = [
            'app' => $app,
            'doc_app' => $app,
            'section' => $sectionMeta,
            'doc_sections' => $this->uiMenuService->getAppSections($name),
        ];

        if ($name === 'documentation' && $section === 'readme') {
            $view['readme_body'] = $this->projectReadmeService->getContent();
            $view['readme_empty'] = $this->projectReadmeService->isEmpty();
            $view['readme_path_label'] = $this->projectReadmeService->getDisplayPath();
        }

        return $this->render($template, $view);
    }

    #[Route('/app/{name}', name: 'app_show', requirements: ['name' => '[a-z0-9_-]+'], methods: ['GET', 'POST'])]
    public function show(string $name, Request $request): Response
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

        if ($name === 'typography') {
            return $this->forward('App\Controller\TypographyController::renderPage', [
                'request' => $request,
            ]);
        }

        if ($name === 'starter') {
            return $this->render('app/starter/index.html.twig', [
                'app' => $app,
                'environment' => $this->starterEnvironmentService->buildReport(),
                'starter_packages' => $this->starterToggleService->listPackages(),
                'starter_services' => $this->starterToggleService->listServices(),
                'starter_log_journal' => $this->starterLogJournalService->buildJournal(),
            ]);
        }

        return $this->render('app/show.html.twig', [
            'app' => $app,
        ]);
    }
}
