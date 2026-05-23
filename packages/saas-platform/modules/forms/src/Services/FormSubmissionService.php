<?php

namespace SaaSPlatform\Modules\Forms\Services;

use SaaSPlatform\Modules\Forms\Contracts\FormSubmissionRepositoryInterface;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use App\Helpers\Jalali;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionService
{
    public function __construct(
        protected FormSubmissionRepositoryInterface $repository
    ) {}

    public function index(array $filters = []): array
    {
        $submissions = $this->repository->getAll($filters);
        $dataKeys = $this->repository->collectDataKeys($submissions);
        $columnOrder = Session::get('submission_column_order', $this->defaultColumnOrder($dataKeys));
        $forms = \SaaSPlatform\Modules\Forms\Models\Form::orderBy('title')->get(['id', 'title']);
        $sites = \App\Models\Site::orderBy('title')->get(['id', 'title', 'domain']);

        return compact('submissions', 'dataKeys', 'columnOrder', 'forms', 'sites');
    }

    public function updateColumnOrder(array $order): void
    {
        if (is_array($order)) {
            Session::put('submission_column_order', $order);
        }
    }

    public function export(array $filters = []): StreamedResponse
    {
        $submissions = $this->repository->getExportData($filters);
        $dataKeys = $this->repository->collectDataKeys($submissions);
        $columnOrder = Session::get('submission_column_order', $this->defaultColumnOrder($dataKeys));

        $filename = 'form-submissions-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($submissions, $columnOrder, $dataKeys) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            $headers = [];
            foreach ($columnOrder as $col) {
                if ($col === 'id') $headers[] = 'شناسه';
                elseif ($col === 'form') $headers[] = 'فرم';
                elseif ($col === 'site') $headers[] = 'سایت';
                elseif ($col === 'date') $headers[] = 'تاریخ';
                elseif ($col === 'ip') $headers[] = 'IP';
                elseif (in_array($col, $dataKeys, true)) $headers[] = $col;
            }
            fputcsv($out, $headers);

            foreach ($submissions as $s) {
                $row = [];
                foreach ($columnOrder as $col) {
                    if ($col === 'id') $row[] = $s->id;
                    elseif ($col === 'form') $row[] = $s->form?->title ?? '';
                    elseif ($col === 'site') $row[] = $s->site?->title ?? $s->site?->domain ?? '';
                    elseif ($col === 'date') $row[] = $s->created_at ? Jalali::formatFa($s->created_at, 'Y/m/d H:i') : '';
                    elseif ($col === 'ip') $row[] = $s->ip ?? '';
                    elseif (in_array($col, $dataKeys, true)) $row[] = $s->data[$col] ?? '';
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function defaultColumnOrder(array $dataKeys): array
    {
        return array_merge(['id', 'form', 'site', 'date', 'ip'], $dataKeys);
    }
}
