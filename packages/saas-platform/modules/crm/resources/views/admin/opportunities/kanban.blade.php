@extends(config('crm.layouts.admin'))

@section('title', 'Kanban فروش')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">Kanban فروش — {{ $pipeline->name }}</h1>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="pipeline_id" class="form-select" onchange="this.form.submit()">
                @foreach ($pipelines as $p)
                    <option value="{{ $p->id }}" @selected($p->id === $pipeline->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <a href="{{ route('crm.admin.opportunities.index', ['siteId' => $siteId, 'pipeline_id' => $pipeline->id]) }}" class="btn btn-outline-secondary">نمای لیستی</a>
        </div>
    </form>

    <div id="crm-kanban-status" class="alert alert-success py-1 px-2 d-none" style="position:fixed; top:10px; left:50%; transform:translateX(-50%); z-index:1050;"></div>

    <div class="d-flex gap-3 overflow-auto pb-3" style="align-items: flex-start;">
        @foreach ($pipeline->stages as $stage)
            <div class="card" style="min-width: 280px; max-width: 280px;">
                <div class="card-header d-flex justify-content-between">
                    <strong>{{ $stage->name }}</strong>
                    <span class="badge bg-secondary">{{ $stage->probability }}%</span>
                </div>
                <div class="card-body kanban-column" data-stage-id="{{ $stage->id }}" style="min-height: 300px;">
                    @foreach ($opportunities->get($stage->id, collect()) as $opportunity)
                        <div class="card mb-2 kanban-card" draggable="true" data-opportunity-id="{{ $opportunity->id }}" style="cursor: grab;">
                            <div class="card-body p-2">
                                <div class="fw-bold small">{{ $opportunity->name }}</div>
                                <div class="text-muted small">{{ $opportunity->contact?->full_name ?? $opportunity->company?->name }}</div>
                                <div class="text-primary small">{{ number_format($opportunity->amount) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const statusBox = document.getElementById('crm-kanban-status');
    let draggedCard = null;

    function showStatus(message, isError) {
        if (!statusBox) return;
        statusBox.textContent = message;
        statusBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusBox.classList.add(isError ? 'alert-danger' : 'alert-success');
        setTimeout(() => statusBox.classList.add('d-none'), 2500);
    }

    document.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => { draggedCard = card; card.style.opacity = '0.5'; });
        card.addEventListener('dragend', () => { card.style.opacity = '1'; });
    });

    document.querySelectorAll('.kanban-column').forEach((column) => {
        column.addEventListener('dragover', (e) => e.preventDefault());

        column.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!draggedCard) return;

            const opportunityId = draggedCard.dataset.opportunityId;
            const stageId = column.dataset.stageId;
            const previousColumn = draggedCard.parentElement;

            column.appendChild(draggedCard);

            fetch(`{{ url('/admin/crm/' . $siteId . '/opportunities') }}/${opportunityId}/move-stage`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ stage_id: stageId }),
            })
                .then((response) => {
                    if (!response.ok) throw new Error('failed');
                    return response.json();
                })
                .then(() => showStatus('مرحله معامله بروزرسانی شد.', false))
                .catch(() => {
                    previousColumn.appendChild(draggedCard);
                    showStatus('خطا در بروزرسانی مرحله — تغییر بازگردانده شد.', true);
                });
        });
    });
})();
</script>
@endsection
