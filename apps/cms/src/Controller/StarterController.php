<?php

namespace App\Controller;

use App\Service\StarterEnvService;
use App\Service\StarterMountService;
use App\Service\StarterToggleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StarterController extends AbstractController
{
    private StarterToggleService $starterToggleService;

    private StarterEnvService $starterEnvService;

    private StarterMountService $starterMountService;

    public function __construct(
        StarterToggleService $starterToggleService,
        StarterEnvService $starterEnvService,
        StarterMountService $starterMountService
    ) {
        $this->starterToggleService = $starterToggleService;
        $this->starterEnvService = $starterEnvService;
        $this->starterMountService = $starterMountService;
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

    #[Route('/app/starter/env', name: 'starter_env_save', methods: ['POST'])]
    public function saveEnv(Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('starter_env', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $scope = $request->request->get('scope');
        if (!is_string($scope)) {
            $scope = '';
        }

        $id = $request->request->get('id');
        if (!is_string($id)) {
            $id = '';
        }

        $rawValues = $request->request->all('values');
        $values = [];
        if (is_array($rawValues)) {
            foreach ($rawValues as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (!is_string($value)) {
                    $value = '';
                }
                $values[$key] = $value;
            }
        }

        try {
            $this->starterEnvService->saveGroupValues($scope, $id, $values);
            $this->addFlash('success', 'ENV сохранены.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    #[Route('/app/starter/mounts/save', name: 'starter_mount_save', methods: ['POST'])]
    public function saveMounts(Request $request): Response
    {
        if (!$this->isValidMountCsrf($request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $rawMounts = $request->request->all('mount');
        if (!is_array($rawMounts)) {
            $rawMounts = [];
        }

        $updated = [];
        foreach ($this->starterMountService->listMounts() as $existing) {
            $id = $existing['id'];
            $row = $rawMounts[$id] ?? null;
            if (!is_array($row)) {
                $updated[] = $existing;
                continue;
            }

            $label = $row['label'] ?? $existing['label'];
            if (!is_string($label)) {
                $label = $existing['label'];
            }

            $hostPath = $row['host_path'] ?? '';
            if (!is_string($hostPath)) {
                $hostPath = '';
            }

            $containerPath = $row['container_path'] ?? $existing['container_path'];
            if (!is_string($containerPath)) {
                $containerPath = $existing['container_path'];
            }

            $readOnly = false;
            if (array_key_exists('read_only', $row)) {
                $readOnly = true;
            }

            $updated[] = [
                'id' => $id,
                'label' => $label,
                'host_path' => $hostPath,
                'container_path' => $containerPath,
                'read_only' => $readOnly,
            ];
        }

        try {
            $this->starterMountService->saveMountsList($updated);
            $written = $this->starterMountService->writeOverrideSnippetFile();
            if ($written) {
                $this->addFlash('success', 'Монтирования сохранены, override обновлён.');
            } else {
                $this->addFlash('success', 'Монтирования сохранены. Файл override на диск не записан — скопируйте фрагмент ниже.');
            }
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    #[Route('/app/starter/mounts/preset', name: 'starter_mount_add_preset', methods: ['POST'])]
    public function addMountPreset(Request $request): Response
    {
        if (!$this->isValidMountCsrf($request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $presetId = $request->request->get('preset_id');
        if (!is_string($presetId)) {
            $presetId = '';
        }

        try {
            $this->starterMountService->addPresetMount($presetId);
            $this->starterMountService->writeOverrideSnippetFile();
            $this->addFlash('success', 'Пресет добавлен.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    #[Route('/app/starter/mounts/add', name: 'starter_mount_add', methods: ['POST'])]
    public function addMount(Request $request): Response
    {
        if (!$this->isValidMountCsrf($request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->starterMountService->addCustomMount();
            $this->addFlash('success', 'Точка монтирования добавлена.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    #[Route('/app/starter/mounts/{id}/remove', name: 'starter_mount_remove', methods: ['POST'])]
    public function removeMount(string $id, Request $request): Response
    {
        if (!$this->isValidMountCsrf($request)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->starterMountService->removeMount($id);
            $this->starterMountService->writeOverrideSnippetFile();
            $this->addFlash('success', 'Точка удалена.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'starter']);
    }

    private function isValidMountCsrf(Request $request): bool
    {
        $token = $request->request->get('_token');
        if (!is_string($token)) {
            return false;
        }

        return $this->isCsrfTokenValid('starter_mount', $token);
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
