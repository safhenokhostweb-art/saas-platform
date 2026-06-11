<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class InstallController extends Controller
{
    public function step1()
    {
        return view('install.step1');
    }

    public function saveDb(Request $request)
    {
        $request->validate([
            'db_host' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\.\-\_]+$/'],
            'db_name' => ['required', 'string', 'max:64',  'regex:/^[a-zA-Z0-9\_\-]+$/'],
            'db_user' => ['required', 'string', 'max:64',  'regex:/^[a-zA-Z0-9\_\-]+$/'],
            'db_pass' => ['nullable', 'string', 'max:128'],
        ]);

        $envPath = base_path('.env');

        if (!file_exists($envPath) || realpath($envPath) !== realpath(base_path('.env'))) {
            abort(500, 'فایل .env در مسیر معتبر یافت نشد.');
        }

        $env = file_get_contents($envPath);

        $env = preg_replace('/^DB_HOST=.*/m',     'DB_HOST='     . $this->quoteEnvValue($request->db_host), $env);
        $env = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=' . $this->quoteEnvValue($request->db_name), $env);
        $env = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=' . $this->quoteEnvValue($request->db_user), $env);
        $env = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=' . $this->quoteEnvValue((string) $request->db_pass), $env);

        // Atomic write: write to tmp then rename to avoid partial-write corruption
        $tmpPath = $envPath . '.tmp.' . getmypid();
        if (file_put_contents($tmpPath, $env, LOCK_EX) === false) {
            abort(500, 'نوشتن در فایل .env ناموفق بود.');
        }
        rename($tmpPath, $envPath);

        Artisan::call('migrate', ['--force' => true]);

        return view('install.step3');
    }

    private function quoteEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        // Quote values that contain spaces or special shell characters
        if (preg_match('/[\s"\'\\\\#]/', $value)) {
            return '"' . addslashes($value) . '"';
        }
        return $value;
    }

    public function saveAdmin(Request $request)
    {
        $request->validate([
            'name'     => 'required',
            'mobile'   => 'required|regex:/^09[0-9]{9}$/',
            'password' => 'required|min:6',
        ]);

        User::create([
            'name'     => $request->name,
            'mobile'   => $request->mobile,
            'password' => Hash::make($request->password),
            'is_admin' => 1,
        ]);

        return redirect()->route('install.done');
    }

    public function done()
    {
        return view('install.done');
    }
}
