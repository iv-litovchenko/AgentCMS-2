<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class NotFoundExceptionSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof HttpExceptionInterface) {
            return;
        }

        if ($throwable->getStatusCode() !== 404) {
            return;
        }

        $message = 'Страница не найдена.';
        if ($throwable instanceof NotFoundHttpException) {
            $exceptionMessage = $throwable->getMessage();
            if ($exceptionMessage !== '' && $exceptionMessage !== 'Not Found') {
                $message = $exceptionMessage;
            }
        } elseif ($throwable->getMessage() !== '') {
            $message = $throwable->getMessage();
        }

        $html = $this->twig->render('errors/not_found.html.twig', [
            'message' => $message,
        ]);

        $event->setResponse(new Response($html, 404));
    }
}
