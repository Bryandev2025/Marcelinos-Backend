<?php

namespace App\Support;

use Composer\CaBundle\CaBundle;

/**
 * Resolves the Guzzle "verify" option so HTTPS works when PHP has no system CA store (typical on Windows).
 */
final class GuzzleVerify
{
    /**
     * @return bool|string Path to CA bundle, false to disable verification (debug only), or true for Guzzle default
     */
    public static function option(): bool|string
    {
        $raw = env('HTTP_CLIENT_VERIFY');

        if ($raw === false || $raw === '0' || $raw === 'false') {
            return false;
        }

        return match (true) {
            $raw === null, $raw === '', $raw === true, $raw === '1', $raw === 'true' => CaBundle::getSystemCaRootBundlePath(),
            default => $raw,
        };
    }
}
