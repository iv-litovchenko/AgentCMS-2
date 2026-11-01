<?php

namespace App\Controller;

use App\Form\DemoFormData;
use App\Form\DemoFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TypographyController extends AbstractController
{
    #[Route('/typography', name: 'typography', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        return $this->renderTypography($request);
    }

    #[Route('/demo-form', name: 'demo_form', methods: ['GET', 'POST'])]
    public function legacyDemoForm(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToTypographyFragment($request, 'demo-form');
        }

        return $this->renderTypography($request);
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

    private function renderTypography(Request $request): Response
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

            $redirectParams = [];
            if ($compact) {
                $redirectParams['compact'] = 1;
            }

            return $this->redirectToRoute('typography', $redirectParams + ['_fragment' => 'demo-form']);
        }

        return $this->render('typography/index.html.twig', [
            'form' => $form->createView(),
            'compact' => $compact,
        ]);
    }

    private function redirectToTypographyFragment(Request $request, string $fragment): Response
    {
        $params = [];
        if ($request->query->getBoolean('compact')) {
            $params['compact'] = 1;
        }

        return $this->redirectToRoute('typography', $params + ['_fragment' => $fragment]);
    }
}
