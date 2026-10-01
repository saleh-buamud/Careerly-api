<?php

namespace App\Enums;

enum UserRole: string
{
    case Employer = 'employer';
    case JobSeeker = 'job_seeker';
    case Admin = 'admin';
}
