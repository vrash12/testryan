<?php

namespace App\Enums;

enum MediaTag: string
{
    case Before = 'Before';
    case During = 'During';
    case After = 'After';
    case Other = 'Other';
}
