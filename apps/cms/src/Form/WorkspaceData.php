<?php

namespace App\Form;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class WorkspaceData
{
    public string $name = '';

    public string $folder = '';

    public string $location = 'local';

    public string $description = '';

    public int $sortOrder = 0;

    public ?UploadedFile $avatarUpload = null;
}
