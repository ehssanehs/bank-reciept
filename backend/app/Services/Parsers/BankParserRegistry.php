<?php

namespace App\Services\Parsers;

use App\Models\Bank;
use App\Services\Parsers\Banks\DefaultBankParser;

/**
 * Resolves the parser implementation for a bank.
 *
 * A bank may specify a custom `parser_class` (strategy/plugin pattern) for its
 * SMS format; otherwise the configurable DefaultBankParser is used.
 */
class BankParserRegistry
{
    private const BUILTIN = [
        DefaultBankParser::class,
    ];

    public function parserFor(Bank $bank): BankParserInterface
    {
        $class = $bank->parser_class;

        if ($class !== null && $class !== '' && class_exists($class)) {
            $instance = app($class);
            if (!$instance instanceof BankParserInterface) {
                throw new \RuntimeException("Parser $class must implement ".BankParserInterface::class);
            }

            return $instance;
        }

        return app(DefaultBankParser::class);
    }

    /** @return array<int,class-string> */
    public static function available(): array
    {
        return self::BUILTIN;
    }
}
