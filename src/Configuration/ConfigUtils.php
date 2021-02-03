<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Configuration;

use Keboola\DbExtractor\Exception\UserException;

class ConfigUtils
{
    public static function base64Decode(string $content, string $parameterName): string
    {
        // Base64 decode
        $content = @base64_decode($content);
        if (!$content) {
            throw new UserException(sprintf('Cannot base64 decode "%s" parameter.', $parameterName));
        }

        return $content;
    }
}
