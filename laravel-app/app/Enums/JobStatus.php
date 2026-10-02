<?php

namespace App\Enums;

enum JobStatus: string
{
    case Unassigned = 'unassigned';
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Completed = 'completed';
}
