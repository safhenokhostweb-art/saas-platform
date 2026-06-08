<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GhasedakGateway implements SmsGatewayInterface
{
    protected string $apiKey;
    protected string $lineNumber;

    public function __construct(array $config)
    {
        $this->apiKey    = $config['api_key']     ?? '';
        $this->lineNumber = $config['line_number'] ?? '';
    }

    public function send(string $to, string $message): bool
    {
        if ($this->apiKey === '') {
            return false;
        }

        try {
            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->post('https://api.ghasedak.me/v2/sms/send/simple', [
                    'receptor'    => $to,
                    'message'     => $message,
                    'linenumber'  => $this->lineNumber,
                ]);

            if ($response->successful()) {
                $status = $response->json('result.code') ?? 0;
                return $status === 200;
            }

            Log::channel('single')->warning('Ghasedak send failed', ['status' => $response->status(), 'to' => $to]);
            return false;
        } catch (\Throwable $e) {
            Log::channel('single')->error('Ghasedak send exception', ['message' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }
}
