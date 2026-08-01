@extends(config('crm.layouts.admin'))

@section('title', 'پیش‌نمایش Import')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">پیش‌نمایش Import — {{ $entity }}</h1>

    @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
        <a href="{{ route('crm.admin.import.show', [$siteId, $entity]) }}" class="btn btn-secondary">بازگشت</a>
    @else
        @php
            $validCount = collect($rows)->where('status', 'valid')->count();
            $duplicateCount = collect($rows)->where('status', 'duplicate')->count();
            $invalidCount = collect($rows)->where('status', 'invalid')->count();
        @endphp

        <p>
            <span class="badge bg-success">معتبر: {{ $validCount }}</span>
            <span class="badge bg-warning text-dark">تکراری: {{ $duplicateCount }}</span>
            <span class="badge bg-danger">نامعتبر: {{ $invalidCount }}</span>
        </p>

        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>وضعیت</th>
                    @foreach ($header as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                    <th>توضیح</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ $row['status'] === 'invalid' ? 'table-danger' : ($row['status'] === 'duplicate' ? 'table-warning' : '') }}">
                        <td>{{ $row['status'] }}</td>
                        @foreach ($header as $col)
                            <td>{{ $row['data'][$col] ?? '' }}</td>
                        @endforeach
                        <td>{{ $row['reason'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <form method="POST" action="{{ route('crm.admin.import.commit', [$siteId, $entity]) }}">
            @csrf
            <input type="hidden" name="path" value="{{ $path }}">
            <button type="submit" class="btn btn-primary" @disabled($validCount === 0)>
                تأیید Import ({{ $validCount }} ردیف معتبر)
            </button>
            <a href="{{ route('crm.admin.import.show', [$siteId, $entity]) }}" class="btn btn-outline-secondary">انصراف</a>
        </form>
    @endif
</div>
@endsection
