<?php

namespace App\Enums;

enum MatchType: string
{
    case STRONG = 'STRONG';
    case ALTERNATIVE_STRONG = 'ALTERNATIVE_STRONG';
    case WEAK = 'WEAK';
    case NONE = 'NONE';
}
