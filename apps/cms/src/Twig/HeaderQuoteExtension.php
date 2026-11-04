<?php

namespace App\Twig;

use App\Service\HeaderQuoteService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class HeaderQuoteExtension extends AbstractExtension implements GlobalsInterface
{
    private HeaderQuoteService $headerQuoteService;

    private ?string $quoteForRequest = null;

    public function __construct(HeaderQuoteService $headerQuoteService)
    {
        $this->headerQuoteService = $headerQuoteService;
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        if ($this->quoteForRequest === null) {
            $this->quoteForRequest = $this->headerQuoteService->pickRandomDisplayText();
        }

        return [
            'header_quote' => $this->quoteForRequest,
        ];
    }
}
