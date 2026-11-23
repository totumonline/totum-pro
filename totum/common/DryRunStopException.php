<?php

namespace totum\common;

/**
 * The dry run stopped at a blocked external action (DryRun::check).
 * Critical: the catch sections of code lines rethrow it, tryCatch too — the execution ends, the transaction is rolled back.
 */
class DryRunStopException extends criticalErrorException
{
    public function __construct(public readonly array $entry)
    {
        parent::__construct('Dry run: stopped at ' . $entry['function'] . ' (' . $entry['kind'] . ') — the external action is blocked');
    }
}
