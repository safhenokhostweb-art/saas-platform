<?php

namespace SaaSPlatform\Modules\Forms\Repositories;

use SaaSPlatform\Modules\Forms\Contracts\FormSubmissionRepositoryInterface;
use SaaSPlatform\Modules\Forms\Models\FormSubmission;
use App\Helpers\Jalali;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FormSubmissionRepository implements FormSubmissionRepositoryInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = FormSubmission::with(['form.fields', 'site'])->latest();

        if (!empty($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }
        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }
        if (!empty($filters['from'])) {
            $fromCarbon = Jalali::toCarbon($filters['from']);
            if ($fromCarbon) {
                $query->whereDate('created_at', '>=', $fromCarbon->format('Y-m-d'));
            }
        }
        if (!empty($filters['to'])) {
            $toCarbon = Jalali::toCarbon($filters['to']);
            if ($toCarbon) {
                $query->whereDate('created_at', '<=', $toCarbon->format('Y-m-d'));
            }
        }
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($qry) use ($q) {
                $qry->whereHas('form', fn ($b) => $b->where('title', 'like', $q))
                    ->orWhereHas('site', fn ($b) => $b->where('title', 'like', $q)->orWhere('domain', 'like', $q))
                    ->orWhere('ip', 'like', $q);
            });
        }

        return $query->paginate(config('forms.pagination', 25))->withQueryString();
    }

    public function getExportData(array $filters = []): Collection
    {
        $query = FormSubmission::with(['form.fields', 'site'])->latest();

        if (!empty($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }
        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }
        if (!empty($filters['from'])) {
            $fromCarbon = Jalali::toCarbon($filters['from']);
            if ($fromCarbon) {
                $query->whereDate('created_at', '>=', $fromCarbon->format('Y-m-d'));
            }
        }
        if (!empty($filters['to'])) {
            $toCarbon = Jalali::toCarbon($filters['to']);
            if ($toCarbon) {
                $query->whereDate('created_at', '<=', $toCarbon->format('Y-m-d'));
            }
        }
        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $query->where(function ($qry) use ($q) {
                $qry->whereHas('form', fn ($b) => $b->where('title', 'like', $q))
                    ->orWhereHas('site', fn ($b) => $b->where('title', 'like', $q)->orWhere('domain', 'like', $q))
                    ->orWhere('ip', 'like', $q);
            });
        }

        return $query->limit(config('forms.export_limit', 10000))->get();
    }

    public function getById(int $id): ?FormSubmission
    {
        return FormSubmission::find($id);
    }

    public function create(array $data): FormSubmission
    {
        return FormSubmission::create($data);
    }

    public function delete(FormSubmission $submission): bool
    {
        return $submission->delete();
    }

    public function collectDataKeys($submissions): array
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
}
