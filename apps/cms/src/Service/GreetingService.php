<?php

namespace App\Service;

use AgentCmsLight\Shared\GreetingService as SharedGreetingService;

class GreetingService
{
    private SharedGreetingService $sharedGreetingService;

    public function __construct()
    {
        $this->sharedGreetingService = new SharedGreetingService();
    }

    public function getMessage(): string
    {
        return $this->sharedGreetingService->getMessage();
    }
}
