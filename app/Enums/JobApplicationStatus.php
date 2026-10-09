<?php

namespace App\Enums;

enum JobApplicationStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function canTransitionTo(self $nextStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($nextStatus, [self::Reviewed, self::Accepted, self::Rejected], true),
            self::Reviewed => in_array($nextStatus, [self::Accepted, self::Rejected], true),
            self::Accepted, self::Rejected => false,
        };
    }
}