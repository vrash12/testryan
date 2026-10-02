<?php

namespace App\Enums;

enum ApprovalStatus: int
{
    case NotApproved = 0;
    case Approved = 1;
    case PendingAdminReview = 2;
}
