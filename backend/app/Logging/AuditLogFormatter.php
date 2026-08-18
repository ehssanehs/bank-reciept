<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

/**
 * Structured JSON formatter used for the dedicated audit log channel.
 * Audit log lines are immutable JSON — they should never be edited.
 */
class AuditLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        $formatter = new JsonFormatter();

        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter($formatter);
        }
    }
}
