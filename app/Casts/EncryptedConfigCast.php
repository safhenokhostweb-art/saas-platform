<?php
namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

class EncryptedConfigCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($value);
            return json_decode($decrypted, true) ?? [];
        } catch (\Throwable $e) {
            return json_decode($value, true) ?? [];
        }
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }
        return Crypt::encryptString(json_encode($value));
    }
}
