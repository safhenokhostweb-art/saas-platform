<?php
namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Models\RemoteAgent;
use App\Services\Infrastructure\RemoteAgentClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RemoteAgentController extends Controller
{
    public function __construct(private readonly RemoteAgentClient $client) {}

    public function index()
    {
        $agents = RemoteAgent::orderByDesc('is_active')->orderBy('name')->get();
        return view('admin.infrastructure.remote-agents.index', compact('agents'));
    }

    public function create()
    {
        return view('admin.infrastructure.remote-agents.form', ['agent' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:128',
            'base_url'    => 'required|url|max:512',
            'secret_key'  => 'required|string|min:16|max:128',
            'environment' => 'required|in:production,staging',
            'server_id'   => 'nullable|integer',
            'notes'       => 'nullable|string|max:1000',
        ]);

        RemoteAgent::create($data + ['is_active' => true]);
        return redirect()->route('admin.infrastructure.remote-agents.index')
            ->with('success', 'Agent ثبت شد.');
    }

    public function edit(RemoteAgent $remoteAgent)
    {
        return view('admin.infrastructure.remote-agents.form', ['agent' => $remoteAgent]);
    }

    public function update(Request $request, RemoteAgent $remoteAgent)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:128',
            'base_url'    => 'required|url|max:512',
            'secret_key'  => 'nullable|string|min:16|max:128',
            'environment' => 'required|in:production,staging',
            'is_active'   => 'boolean',
            'server_id'   => 'nullable|integer',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // Keep existing secret if not provided
        if (empty($data['secret_key'])) {
            unset($data['secret_key']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $remoteAgent->update($data);

        return redirect()->route('admin.infrastructure.remote-agents.index')
            ->with('success', 'Agent به‌روز شد.');
    }

    public function destroy(RemoteAgent $remoteAgent)
    {
        $remoteAgent->delete();
        return redirect()->route('admin.infrastructure.remote-agents.index')
            ->with('success', 'Agent حذف شد.');
    }

    // ── Agent commands ────────────────────────────────────────────────────────

    public function checkHealth(RemoteAgent $remoteAgent)
    {
        $result = $this->client->health($remoteAgent);
        return response()->json($result);
    }

    public function triggerBackup(RemoteAgent $remoteAgent)
    {
        $result = $this->client->triggerBackup($remoteAgent);
        return response()->json($result);
    }

    public function triggerDeploy(Request $request, RemoteAgent $remoteAgent)
    {
        $options = $request->only(['branch', 'migrate', 'seed']);
        $result  = $this->client->triggerDeploy($remoteAgent, $options);
        return response()->json($result);
    }

    public function triggerRestart(Request $request, RemoteAgent $remoteAgent)
    {
        $service = $request->input('service', 'php-fpm');
        $result  = $this->client->triggerRestart($remoteAgent, $service);
        return response()->json($result);
    }

    public function generateSecret()
    {
        return response()->json(['secret' => Str::random(64)]);
    }
}
