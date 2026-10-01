<?php

namespace App\Enums;

enum EmployerType: string
{
    case Individual = 'individual';
    case Company = 'company';
    case Organization = 'organization';
}
