<?php

namespace SaaSPlatform\Modules\Forms\Repositories;

use SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface;
use SaaSPlatform\Modules\Forms\Models\Form;
use Illuminate\Pagination\LengthAwarePaginator;

class FormRepository implements FormRepositoryInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Form::withCount('submissions')
            ->withCount(['fields' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['automations' => fn ($q) => $q->where('is_active', true)])
            ->latest();

        return $query->paginate(config('forms.pagination', 25))->withQueryString();
    }

    public function getById(int $id): ?Form
    {
        return Form::find($id);
    }

    public function create(array $data): Form
    {
        return Form::create($data)->fresh();
    }

    public function update(Form $form, array $data): Form
    {
        $form->update($data);
        return $form->fresh();
    }

    public function delete(Form $form): bool
    {
        $form->fields()->delete();
        $form->submissions()->delete();
        return $form->delete();
    }

    public function getWithFields(int $id): ?Form
    {
        return Form::with('fields')->find($id);
    }

    public function getActiveForms(): iterable
    {
        return Form::where('is_active', true)->orderBy('title')->get(['id', 'title', 'slug']);
    }
}
