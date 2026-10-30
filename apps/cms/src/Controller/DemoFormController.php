<?php

namespace App\Controller;

use App\Form\DemoFormData;
use App\Form\DemoFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DemoFormController extends AbstractController
{
    #[Route('/demo-form', name: 'demo_form', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
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

            return $this->redirectToRoute('demo_form', $redirectParams);
        }

        return $this->render('demo/form/index.html.twig', [
            'form' => $form->createView(),
            'compact' => $compact,
        ]);
    }
}
