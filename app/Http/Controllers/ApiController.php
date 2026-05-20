<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiController extends Controller
{
    public function fetchData()
    {
        $url = 'https://my.fibonacci.monster/api/v1/chat/completions';
        $token = 'd1ea8b521f227db532b05f6e83826d9cc1800294309be1344af0e86533383d27404c0d3e7c33720f';

        $response = Http::withToken($token)->get($url);

        return response()->json(['data' => $response->json()]);
    }
}
