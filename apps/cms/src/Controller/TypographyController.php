<?php

namespace App\Controller;

use App\Form\DemoFormData;
use App\Form\DemoFormType;
use App\Service\UiMenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TypographyController extends AbstractController
{
    private UiMenuService $uiMenuService;

    public function __construct(UiMenuService $uiMenuService)
    {
        $this->uiMenuService = $uiMenuService;
    }

    #[Route('/typography', name: 'typography', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToTypography($request);
        }

        return $this->renderPage($request);
    }

    #[Route('/demo-form', name: 'demo_form', methods: ['GET', 'POST'])]
    public function legacyDemoForm(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToTypographyFragment($request, 'demo-form');
        }

        return $this->renderPage($request);
    }

    #[Route('/tailwind-demo', name: 'tailwind_demo', methods: ['GET'])]
    public function legacyTailwindDemo(Request $request): Response
    {
        return $this->redirectToTypographyFragment($request, 'tailwind');
    }

    #[Route('/flowbite-demo', name: 'flowbite_demo', methods: ['GET'])]
    public function legacyFlowbiteDemo(Request $request): Response
    {
        return $this->redirectToTypographyFragment($request, 'flowbite');
    }

    public function renderPage(Request $request): Response
    {
        $compact = $request->query->getBoolean('compact');
        $data = new DemoFormData();
        $form = $this->createForm(DemoFormType::class, $data);
        $form->handleRequest($request);

        $routeContext = $this->resolveRouteContext($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var DemoFormData $submitted */
            $submitted = $form->getData();
            $fileName = '';
            if ($submitted->attachment !== null) {
                $fileName = $submitted->attachment->getClientOriginalName();
            }

            $dueDateText = '';
            if ($submitted->dueDate !== null) {
                $dueDateText = $submitted->dueDate->format('Y-m-d');
            }

            $message = sprintf(
                'Форма принята: «%s», email %s, категория %s, приоритет %s, теги: %s, файл: %s, дата: %s',
                $submitted->title,
                $submitted->contactEmail,
                (string) $submitted->category,
                (string) $submitted->priority,
                implode(', ', $submitted->tags),
                $fileName,
                $dueDateText
            );

            $this->addFlash('success', $message);

            $redirectParams = $routeContext['route_params'];
            if ($compact) {
                $redirectParams['compact'] = 1;
            }

            return $this->redirectToRoute($routeContext['route_name'], $redirectParams + ['_fragment' => 'demo-form']);
        }

        $view = [
            'form' => $form->createView(),
            'compact' => $compact,
            'typography_route_name' => $routeContext['route_name'],
            'typography_route_params' => $routeContext['route_params'],
        ];

        if ($routeContext['standalone']) {
            $app = $this->uiMenuService->findAppByName('typography');
            if ($app === null) {
                throw $this->createNotFoundException('Приложение не найдено.');
            }
            $view['cms_app'] = $app;
            $view['hide_apps_nav'] = true;
            $template = 'typography/shell_app.html.twig';
        } else {
            $view['doc_app'] = $this->uiMenuService->findAppByName('documentation');
            $view['doc_sections'] = $this->uiMenuService->getAppSections('documentation');
            $view['section'] = $this->uiMenuService->findAppSection('documentation', 'typography');
            $template = 'typography/shell_doc.html.twig';
        }

        return $this->render($template, $view);
    }

    /**
     * @return array{standalone: bool, route_name: string, route_params: array<string, string|int>}
     */
    private function resolveRouteContext(Request $request): array
    {
        $route = $request->attributes->get('_route');
        if (!is_string($route)) {
            $route = '';
        }

        if ($route === 'app_show') {
            $name = $request->attributes->get('name');
            if ($name === 'typography') {
                return [
                    'standalone' => true,
                    'route_name' => 'app_show',
                    'route_params' => ['name' => 'typography'],
                ];
            }
        }

        return [
            'standalone' => false,
            'route_name' => 'app_section',
            'route_params' => [
                'name' => 'documentation',
                'section' => 'typography',
            ],
        ];
    }

    private function redirectToTypography(Request $request): Response
    {
        $params = ['name' => 'typography'];
        if ($request->query->getBoolean('compact')) {
            $params['compact'] = 1;
        }

        return $this->redirectToRoute('app_show', $params, Response::HTTP_MOVED_PERMANENTLY);
    }

    private function redirectToTypographyFragment(Request $request, string $fragment): Response
    {
        $params = ['name' => 'typography'];
        if ($request->query->getBoolean('compact')) {
            $params['compact'] = 1;
        }

        return $this->redirectToRoute('app_show', $params + ['_fragment' => $fragment]);
    }
}
