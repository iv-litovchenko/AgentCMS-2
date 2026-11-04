<?php

namespace App\Twig;

use App\Service\FooterQuoteService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class FooterQuoteExtension extends AbstractExtension implements GlobalsInterface
{
    private FooterQuoteService $footerQuoteService;

    private ?string $quoteForRequest = null;

    public function __construct(FooterQuoteService $footerQuoteService)
    {
        $this->footerQuoteService = $footerQuoteService;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        if ($this->quoteForRequest === null) {
            $this->quoteForRequest = $this->footerQuoteService->pickRandomText();
        }

        return [
            'footer_quote' => $this->quoteForRequest,
        ];
    }
}
