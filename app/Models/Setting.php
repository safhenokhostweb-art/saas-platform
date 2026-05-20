<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        try {
            return static::where('key', $key)->value('value') ?? $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
