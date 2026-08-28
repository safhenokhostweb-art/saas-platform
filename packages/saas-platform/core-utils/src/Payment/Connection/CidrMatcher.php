<?php

namespace SaaSPlatform\CoreUtils\Payment\Connection;

final class CidrMatcher
{
    /** @param array<int, string> $cidrs */
    public function contains(string $ip, array $cidrs): bool
    {
        $packedIp = @inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        foreach ($cidrs as $cidr) {
            [$network, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);
            $packedNetwork = @inet_pton($network);
            if ($packedNetwork === false || strlen($packedNetwork) !== strlen($packedIp)) {
                continue;
            }

            $maxBits = strlen($packedIp) * 8;
            $prefixLength = $prefix === null ? $maxBits : filter_var($prefix, FILTER_VALIDATE_INT);
            if ($prefixLength === false || $prefixLength < 0 || $prefixLength > $maxBits) {
                continue;
            }

            $wholeBytes = intdiv($prefixLength, 8);
            $remainingBits = $prefixLength % 8;
            if (substr($packedIp, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
                continue;
            }

            if ($remainingBits === 0) {
                return true;
            }

            $mask = (0xff << (8 - $remainingBits)) & 0xff;
            if ((ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
