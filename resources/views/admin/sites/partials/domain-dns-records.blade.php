{{-- Last DNS check of one SiteDomain ($row->dns_report from SiteDomainService::checkDns()). --}}
@php($records = $row->dns_report['records'] ?? null)
@if($records)
    <div class="overflow-x-auto">
        <table class="table table-sm text-sm mb-1">
            <thead>
                <tr><th>نام</th><th>میزبان</th><th>پاسخ فعلی DNS</th><th>وضعیت</th></tr>
            </thead>
            <tbody>
                @foreach($records as $record)
                    <tr>
                        <td class="font-mono" dir="ltr">{{ $record['name'] }}</td>
                        <td class="font-mono" dir="ltr">{{ $record['host'] }}</td>
                        <td class="font-mono" dir="ltr">{{ $record['resolved'] ? implode(', ', $record['resolved']) : '—' }}</td>
                        <td>
                            @if($record['ok'])
                                <span class="text-emerald-700 font-semibold">متصل</span>
                            @else
                                <span class="text-amber-700 font-semibold">متصل نیست</span>
                                @if($record['message'])<div class="text-xs text-gray-500 leading-5">{{ $record['message'] }}</div>@endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mb-0">آخرین بررسی: {{ $row->dns_checked_at ? datetime_fa($row->dns_checked_at) : '—' }}</p>
@else
    <p class="text-sm text-gray-500 mb-0">هنوز بررسی DNS انجام نشده است.</p>
@endif
