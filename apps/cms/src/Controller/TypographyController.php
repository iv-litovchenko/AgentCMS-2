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
            return $this->redirectToDocumentationTypography($request);
        }

        return $this->renderPage($request);
    }

    #[Route('/demo-form', name: 'demo_form', methods: ['GET', 'POST'])]
    public function legacyDemoForm(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToDocumentationTypographyFragment($request, 'demo-form');
        }

        return $this->renderPage($request);
    }

    #[Route('/tailwind-demo', name: 'tailwind_demo', methods: ['GET'])]
    public function legacyTailwindDemo(Request $request): Response
    {
        return $this->redirectToDocumentationTypographyFragment($request, 'tailwind');
    }

    #[Route('/flowbite-demo', name: 'flowbite_demo', methods: ['GET'])]
    public function legacyFlowbiteDemo(Request $request): Response
    {
        return $this->redirectToDocumentationTypographyFragment($request, 'flowbite');
    }

    public function renderPage(Request $request): Response
    {
        $compact = $request->query->getBoolean('compact');
        $data = new DemoFormData();
        $form = $this->createForm(DemoFormType::class, $data);
        $form->handleRequest($request);

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

            $redirectParams = [
                'name' => 'documentation',
                'section' => 'typography',
            ];
            if ($compact) {
                $redirectParams['compact'] = 1;
            }

            return $this->redirectToRoute('app_section', $redirectParams + ['_fragment' => 'demo-form']);
        }

        return $this->render('typography/index.html.twig', [
            'form' => $form->createView(),
            'compact' => $compact,
            'doc_app' => $this->uiMenuService->findAppByName('documentation'),
            'doc_sections' => $this->uiMenuService->getAppSections('documentation'),
            'section' => $this->uiMenuService->findAppSection('documentation', 'typography'),
        ]);
    }

    private function redirectToDocumentationTypography(Request $request): Response
    {
        $params = [
            'name' => 'documentation',
            'section' => 'typography',
        ];
        if ($request->query->getBoolean('compact')) {
            $params['compact'] = 1;
        }

        return $this->redirectToRoute('app_section', $params, Response::HTTP_MOVED_PERMANENTLY);
    }

    private function redirectToDocumentationTypographyFragment(Request $request, string $fragment): Response
    {
        $params = [
            'name' => 'documentation',
            'section' => 'typography',
        ];
        if ($request->query->getBoolean('compact')) {
            $params['compact'] = 1;
        }

        return $this->redirectToRoute('app_section', $params + ['_fragment' => $fragment]);
    }
}
