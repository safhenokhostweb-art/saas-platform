<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Http\Controllers\Controller;
use App\Jobs\CustomerDeletionPipelineJob;
use App\Models\CustomerDeletion;
use App\Models\CustomerInstallation;
use App\Services\CustomerDeletion\Dto\DeletePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class CustomerDeleteController extends Controller
{
    /**
     * Show the delete confirmation form with policy selection.
     * GET /admin/infrastructure/customer-deployment/installations/{installation}/delete
     */
    public function confirm(CustomerInstallation $installation)
    {
        if ($installation->isRunning()) {
            return back()->with('error', 'نمی‌توان نصب در حال اجرا را حذف کرد.');
        }

        // Check if a deletion already exists
        $existing = CustomerDeletion::where('installation_id', $installation->id)
            ->whereIn('status', ['pending', 'running', 'complete'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('admin.customer-deployment.deletions.progress', $existing)
                ->with('info', 'یک فرآیند حذف برای این نصب وجود دارد.');
        }

        $policies = DeletePolicy::options();

        return view('admin.infrastructure.customer-deployment.delete-confirm', compact(
            'installation', 'policies'
        ));
    }

    /**
     * Initiate the deletion pipeline.
     * POST /admin/infrastructure/customer-deployment/installations/{installation}/delete
     */
    public function initiate(Request $request, CustomerInstallation $installation)
    {
        if ($installation->isRunning()) {
            return back()->with('error', 'نمی‌توان نصب در حال اجرا را حذف کرد.');
        }

        $request->validate([
            'policy'      => 'required|in:' . implode(',', array_column(DeletePolicy::cases(), 'value')),
            'confirm_domain' => 'required|string',
        ]);

        // Confirm the operator typed the domain correctly
        if (trim($request->confirm_domain) !== $installation->customer_domain) {
            return back()
                ->withInput()
                ->with('error', 'دامنه وارد شده با دامنه نصب مطابقت ندارد.');
        }

        $deletion = CustomerDeletion::create([
            'installation_id' => $installation->id,
            'site_id'         => $installation->site_id,
            'initiated_by'    => Auth::id(),
            'policy'          => $request->policy,
            'status'          => 'pending',
        ]);

        CustomerDeletionPipelineJob::dispatch($deletion->id)
            ->onConnection('customer-install')
            ->onQueue('customer-install');

        return redirect()
            ->route('admin.customer-deployment.deletions.progress', $deletion)
            ->with('success', 'فرآیند حذف مشتری آغاز شد.');
    }

    /**
     * Show deletion progress with SSE monitor.
     * GET /admin/infrastructure/customer-deployment/deletions/{deletion}
     */
    public function progress(CustomerDeletion $deletion)
    {
        $deletion->load(['installation', 'logs']);
        $policyLabel = DeletePolicy::from($deletion->policy)->label();

        return view('admin.infrastructure.customer-deployment.delete-progress', compact(
            'deletion', 'policyLabel'
        ));
    }

    /**
     * SSE stream for real-time deletion progress.
     * GET /admin/infrastructure/customer-deployment/deletions/{deletion}/stream
     */
    public function stream(CustomerDeletion $deletion)
    {
        return response()->stream(function () use ($deletion) {
            $key    = "customer_deletion_progress:{$deletion->id}";
            $cursor = 0;
            $maxIdle = 60;
            $idled   = 0;

            while (true) {
                if (connection_aborted()) break;

                try {
                    $messages = Redis::lrange($key, $cursor, -1);
                } catch (\Throwable) {
                    $messages = [];
                }

                foreach ($messages as $msg) {
                    echo "data: {$msg}\n\n";
                    ob_flush();
                    flush();
                    $cursor++;
                    $idled = 0;
                }

                // Reload to check terminal status
                $deletion->refresh();
                if ($deletion->isComplete() || $deletion->isFailed()) {
                    echo "data: " . json_encode(['event' => 'terminal', 'status' => $deletion->status]) . "\n\n";
                    ob_flush();
                    flush();
                    break;
                }

                if (empty($messages)) {
                    $idled++;
                    if ($idled >= $maxIdle) break;
                    echo ": heartbeat\n\n";
                    ob_flush();
                    flush();
                }

                usleep(800000); // 0.8s poll
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
