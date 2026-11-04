<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class HttpsRedirectSubscriber implements EventSubscriberInterface
{
    private bool $redirectHttpToHttps;

    private string $publicHttpsPort;

    public function __construct(bool $redirectHttpToHttps, string $publicHttpsPort)
    {
        $this->redirectHttpToHttps = $redirectHttpToHttps;
        $this->publicHttpsPort = $publicHttpsPort;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 48],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->redirectHttpToHttps) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isSecure()) {
            return;
        }

        $path = $request->getPathInfo();
        if ($path === '/health') {
            return;
        }

        $target = $this->buildHttpsUrl($request);
        $event->setResponse(new RedirectResponse($target, RedirectResponse::HTTP_MOVED_PERMANENTLY));
    }

    private function buildHttpsUrl(Request $request): string
    {
        $host = $request->getHost();
        $portSuffix = '';
        if ($this->publicHttpsPort !== '' && $this->publicHttpsPort !== '443') {
            $portSuffix = ':' . $this->publicHttpsPort;
        }

        return 'https://' . $host . $portSuffix . $request->getRequestUri();
    }
}
