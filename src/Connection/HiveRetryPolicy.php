<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Connection;

use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryContextInterface;

class HiveRetryPolicy extends SimpleRetryPolicy
{
    public function canRetry(RetryContextInterface $context): bool
    {
        $e = $context->getLastException();

        // Don't retry "SemanticException"
        if ($e && strpos($e->getMessage(), 'SemanticException') !== false) {
            return false;
        }

        return parent::canRetry($context);
    }
}
