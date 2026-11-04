<?php

namespace App\EventSubscriber;

use App\Service\CmsFileLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CmsExceptionLogSubscriber implements EventSubscriberInterface
{
    private CmsFileLogger $cmsFileLogger;

    public function __construct(CmsFileLogger $cmsFileLogger)
    {
        $this->cmsFileLogger = $cmsFileLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -90],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        $statusCode = 500;
        if ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();
        }

        if ($statusCode < 500) {
            return;
        }

        $message = $throwable->getMessage();
        if ($message === '') {
            $message = get_class($throwable);
        }

        $this->cmsFileLogger->error(sprintf('HTTP %d — %s', $statusCode, $message));
    }
}
