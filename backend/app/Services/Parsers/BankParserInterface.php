<?php

namespace App\Services\Parsers;

use App\Models\Bank;

interface BankParserInterface
{
    /**
     * Parse a raw bank SMS into structured data.
     *
     * Implementations MUST preserve the raw message and MUST NOT assume a
     * single fixed bank format. Unknown values are returned as null — the
     * caller decides whether the parse was "good enough".
     */
    public function parse(Bank $bank, string $rawMessage): SmsParsedData;
}
