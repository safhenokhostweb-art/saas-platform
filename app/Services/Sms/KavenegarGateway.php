<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KavenegarGateway implements SmsGatewayInterface
{
    protected string $apiKey;
    protected string $sender;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->sender = $config['sender'] ?? '';
    }

    public function send(string $to, string $message): bool
    {
        if ($this->apiKey === '') {
            return false;
        }

        try {
            $response = Http::post(
                "https://api.kavenegar.com/v1/{$this->apiKey}/sms/send.json",
                ['receptor' => $to, 'message' => $message, 'sender' => $this->sender]
            );

            if ($response->successful()) {
                $returnCode = $response->json('return.status') ?? 0;
                return $returnCode === 200;
            }

            Log::channel('single')->warning('Kavenegar send failed', ['status' => $response->status(), 'to' => $to]);
            return false;
        } catch (\Throwable $e) {
            Log::channel('single')->error('Kavenegar send exception', ['message' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }
}
