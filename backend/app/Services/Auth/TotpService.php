<?php

namespace App\Services\Auth;

// RFC 6238 TOTP without extra deps (SHA1, 30s step, 6 digits).
class TotpService
{
    public function generateSecret(int $bytes = 20): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function provisioningUri(string $email, string $secret, string $issuer = 'cipherSOC'): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$email).'?secret='.$this->toBase32($secret).'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $t = intdiv(time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->hotp($secret, $t + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $key = $this->toBase32($secret, true);
        $msg = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($code % 1000000), 6, '0', STR_PAD_LEFT);
    }

    // Store secret as base64url internally; convert to base32 for authenticator apps.
    private function toBase32(string $secret, bool $decode = false): string
    {
        if ($decode) {
            $b64 = strtr($secret, '-_', '+/');
            $pad = strlen($b64) % 4;
            if ($pad) {
                $b64 .= str_repeat('=', 4 - $pad);
            }

            return base64_decode($b64) ?: '';
        }
        $raw = base64_decode(strtr($secret, '-_', '+/')) ?: $secret;
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($raw) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }

        return $out;
    }
}
