<?php

namespace App\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class DemoFormData
{
    public string $title = '';

    public string $description = '';

    public string $contactEmail = '';

    public string $secret = '';

    public ?int $quantity = null;

    public ?string $category = null;

    public ?string $priority = null;

    /** @var list<string> */
    public array $tags = [];

    public bool $agreeTerms = false;

    public bool $enableNotifications = false;

    public int $progressLevel = 50;

    public ?UploadedFile $attachment = null;

    public ?\DateTimeInterface $dueDate = null;

    public string $token = 'demo-token';

    public string $referenceCode = 'REF-001';
}
