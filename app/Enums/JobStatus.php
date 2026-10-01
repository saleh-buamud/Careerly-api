<?php

namespace App\Enums;

enum JobStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
