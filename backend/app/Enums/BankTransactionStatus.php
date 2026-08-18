<?php

namespace App\Enums;

enum BankTransactionStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case RESERVED = 'RESERVED';
    case USED = 'USED';
}
