<?php

namespace App\Controller;

use App\Service\StarterToggleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StarterController extends AbstractController
{
    private StarterToggleService $starterToggleService;

    public function __construct(StarterToggleService $starterToggleService)
    {
        $this->starterToggleService = $starterToggleService;
    }

    #[Route('/ajax/starter/toggle', name: 'ajax_starter_toggle', methods: ['POST'])]
    public function ajaxToggle(Request $request): Response
    {
        if (!$this->isAjaxRequest($request)) {
            return new JsonResponse(['error' => 'Expected AJAX request.'], Response::HTTP_BAD_REQUEST);
        }

        $validation = $this->validateToggleRequest($request);
        if ($validation instanceof Response) {
            return $validation;
        }

        $kind = $validation['kind'];
        $id = $validation['id'];
        $enabled = $validation['enabled'];

        try {
            if ($kind === 'package') {
                $this->starterToggleService->setPackageEnabled($id, $enabled);
            } else {
                $this->starterToggleService->setServiceEnabled($id, $enabled);
            }
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'ok' => true,
            'kind' => $kind,
            'id' => $id,
            'enabled' => $enabled,
        ]);
    }

    #[Route('/app/starter/toggle', name: 'starter_toggle', methods: ['POST'])]
    public function formToggle(Request $request): Response
    {
        $validation = $this->validateToggleRequest($request);
        if ($validation instanceof Response) {
            return $validation;
        }

        $kind = $validation['kind'];
        $id = $validation['id'];
        $enabled = $validation['enabled'];

        try {
            if ($kind === 'package') {
                $this->starterToggleService->setPackageEnabled($id, $enabled);
            } else {
                $this->starterToggleService->setServiceEnabled($id, $enabled);
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    /**
     * @return array{kind: string, id: string, enabled: bool}|Response
     */
    private function validateToggleRequest(Request $request)
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('starter_toggle', $token)) {
            if ($this->isAjaxRequest($request)) {
                return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
            }

            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $kind = $request->request->get('kind');
        if (!is_string($kind) || ($kind !== 'package' && $kind !== 'service')) {
            if ($this->isAjaxRequest($request)) {
                return new JsonResponse(['error' => 'Invalid kind.'], Response::HTTP_BAD_REQUEST);
            }

            throw $this->createNotFoundException('Invalid kind.');
        }

        $id = $request->request->get('id');
        if (!is_string($id) || $id === '') {
            if ($this->isAjaxRequest($request)) {
                return new JsonResponse(['error' => 'Invalid id.'], Response::HTTP_BAD_REQUEST);
            }

            throw $this->createNotFoundException('Invalid id.');
        }

        $enabledRaw = $request->request->get('enabled');
        $enabled = false;
        if ($enabledRaw === '1' || $enabledRaw === 1 || $enabledRaw === true || $enabledRaw === 'true') {
            $enabled = true;
        }

        return [
            'kind' => $kind,
            'id' => $id,
            'enabled' => $enabled,
        ];
    }

    private function isAjaxRequest(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = $request->headers->get('Accept');
        if (is_string($accept) && str_contains($accept, 'application/json')) {
            return true;
        }

        return false;
    }
}
