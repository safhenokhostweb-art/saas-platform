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
            'db_host' => 'required',
            'db_name' => 'required',
            'db_user' => 'required',
            'db_pass' => 'nullable',
        ]);

        $envPath = base_path('.env');

        $env = file_get_contents($envPath);

        $env = preg_replace('/DB_HOST=.*/', 'DB_HOST='.$request->db_host, $env);
        $env = preg_replace('/DB_DATABASE=.*/', 'DB_DATABASE='.$request->db_name, $env);
        $env = preg_replace('/DB_USERNAME=.*/', 'DB_USERNAME='.$request->db_user, $env);
        $env = preg_replace('/DB_PASSWORD=.*/', 'DB_PASSWORD='.$request->db_pass, $env);

        file_put_contents($envPath, $env);

        Artisan::call('migrate', ['--force' => true]);

        return view('install.step3');
    }

    public function saveAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
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
