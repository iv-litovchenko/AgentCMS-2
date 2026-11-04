<?php

namespace App\Controller;

use App\Service\ConnectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConnectorController extends AbstractController
{
    private ConnectorService $connectorService;

    public function __construct(ConnectorService $connectorService)
    {
        $this->connectorService = $connectorService;
    }

    #[Route('/app/connector/tokens', name: 'connector_tokens_save', methods: ['POST'])]
    public function saveTokens(Request $request): Response
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('connector_tokens', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $rawValues = $request->request->all('tokens');
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
            $this->connectorService->saveTokenValues($values);
            $this->addFlash('success', 'Токены сохранены.');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_show', ['name' => 'connector']);
    }
}
