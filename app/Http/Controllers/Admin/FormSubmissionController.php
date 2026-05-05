<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Jalali;
use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $query = FormSubmission::with(['form.fields', 'site'])->latest();

        if ($request->filled('form_id')) {
            $query->where('form_id', $request->form_id);
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }
        if ($request->filled('from')) {
            $fromCarbon = Jalali::toCarbon($request->from);
            if ($fromCarbon) {
                $query->whereDate('created_at', '>=', $fromCarbon->format('Y-m-d'));
            }
        }
        if ($request->filled('to')) {
            $toCarbon = Jalali::toCarbon($request->to);
            if ($toCarbon) {
                $query->whereDate('created_at', '<=', $toCarbon->format('Y-m-d'));
            }
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($qry) use ($q) {
                $qry->whereHas('form', fn ($b) => $b->where('title', 'like', $q))
                    ->orWhereHas('site', fn ($b) => $b->where('title', 'like', $q)->orWhere('domain', 'like', $q))
                    ->orWhere('ip', 'like', $q);
            });
        }

        $submissions = $query->paginate(25)->withQueryString();

        $dataKeys = $this->collectDataKeys($submissions);
        $columnOrder = Session::get('submission_column_order', $this->defaultColumnOrder($dataKeys));
        $forms = Form::orderBy('title')->get(['id', 'title']);
        $sites = Site::orderBy('title')->get(['id', 'title', 'domain']);

        return view('admin.forms.submissions.index', compact('submissions', 'dataKeys', 'columnOrder', 'forms', 'sites'));
    }

    public function updateColumnOrder(Request $request)
    {
        $order = $request->input('order', []);
        if (is_array($order)) {
            Session::put('submission_column_order', $order);
        }
        return response()->json(['ok' => true]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = FormSubmission::with(['form.fields', 'site'])->latest();

        if ($request->filled('form_id')) {
            $query->where('form_id', $request->form_id);
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }
        if ($request->filled('from')) {
            $fromCarbon = Jalali::toCarbon($request->from);
            if ($fromCarbon) {
                $query->whereDate('created_at', '>=', $fromCarbon->format('Y-m-d'));
            }
        }
        if ($request->filled('to')) {
            $toCarbon = Jalali::toCarbon($request->to);
            if ($toCarbon) {
                $query->whereDate('created_at', '<=', $toCarbon->format('Y-m-d'));
            }
        }
        if ($request->filled('q')) {
            $q = '%' . $request->q . '%';
            $query->where(function ($qry) use ($q) {
                $qry->whereHas('form', fn ($b) => $b->where('title', 'like', $q))
                    ->orWhereHas('site', fn ($b) => $b->where('title', 'like', $q)->orWhere('domain', 'like', $q))
                    ->orWhere('ip', 'like', $q);
            });
        }

        $submissions = $query->limit(10000)->get();
        $dataKeys = $this->collectDataKeysFromCollection($submissions);
        $columnOrder = Session::get('submission_column_order', $this->defaultColumnOrder($dataKeys));

        $filename = 'form-submissions-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($submissions, $columnOrder, $dataKeys) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

            $headers = [];
            foreach ($columnOrder as $col) {
                if ($col === 'id') {
                    $headers[] = 'شناسه';
                } elseif ($col === 'form') {
                    $headers[] = 'فرم';
                } elseif ($col === 'site') {
                    $headers[] = 'سایت';
                } elseif ($col === 'date') {
                    $headers[] = 'تاریخ';
                } elseif ($col === 'ip') {
                    $headers[] = 'IP';
                } elseif (in_array($col, $dataKeys, true)) {
                    $headers[] = $col;
                }
            }
            fputcsv($out, $headers);

            foreach ($submissions as $s) {
                $row = [];
                foreach ($columnOrder as $col) {
                    if ($col === 'id') {
                        $row[] = $s->id;
                    } elseif ($col === 'form') {
                        $row[] = $s->form?->title ?? '';
                    } elseif ($col === 'site') {
                        $row[] = $s->site?->title ?? $s->site?->domain ?? '';
                    } elseif ($col === 'date') {
                        $row[] = $s->created_at ? Jalali::formatFa($s->created_at, 'Y/m/d H:i') : '';
                    } elseif ($col === 'ip') {
                        $row[] = $s->ip ?? '';
                    } elseif (in_array($col, $dataKeys, true)) {
                        $row[] = $s->data[$col] ?? '';
                    }
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function collectDataKeys($submissions): array
    {
        $keys = [];
        foreach ($submissions as $s) {
            if (is_array($s->data)) {
                foreach (array_keys($s->data) as $k) {
                    $keys[$k] = true;
                }
            }
        }
        return array_keys($keys);
    }

    private function collectDataKeysFromCollection($submissions): array
    {
        $keys = [];
        foreach ($submissions as $s) {
            if (is_array($s->data)) {
                foreach (array_keys($s->data) as $k) {
                    $keys[$k] = true;
                }
            }
        }
        return array_keys($keys);
    }

    private function defaultColumnOrder(array $dataKeys): array
    {
        return array_merge(['id', 'form', 'site', 'date', 'ip'], $dataKeys);
    }
}
