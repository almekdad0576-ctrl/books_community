<?php

namespace App\Enums;

enum BookStatus: string
{
    case PENDING_UPLOAD = 'pending_upload';
    case ACTIVE = 'active';
}
