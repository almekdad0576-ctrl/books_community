<?php

namespace App\Enums;

enum FileType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case OTHER = 'other';
}
