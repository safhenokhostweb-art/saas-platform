@php
$score      = $readinessScore['score'];
$grade      = $readinessScore['grade'];
$color      = $readinessScore['color'];
$components = $readinessScore['components'];
$issues     = $readinessScore['issues'];

$ringColor = match($color) {
    'green' => ['stroke' => '#22c55e', 'text' => 'text-green-600', 'bg' => 'bg-green-50', 'border' => 'border-green-200', 'badge' => 'bg-green-100 text-green-700'],
    'blue'  => ['stroke' => '#3b82f6', 'text' => 'text-blue-600',  'bg' => 'bg-blue-50',  'border' => 'border-blue-200',  'badge' => 'bg-blue-100 text-blue-700'],
    'amber' => ['stroke' => '#f59e0b', 'text' => 'text-amber-600', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-700'],
    default => ['stroke' => '#ef4444', 'text' => 'text-red-600',   'bg' => 'bg-red-50',   'border' => 'border-red-200',   'badge' => 'bg-red-100 text-red-700'],
};

$statusColor = fn(string $s) => match($s) {
    'OK'      => 'bg-green-100 text-green-700',
    'WARN'    => 'bg-amber-100 text-amber-700',
    'UNKNOWN' => 'bg-gray-100 text-gray-600',
    default   => 'bg-red-100 text-red-700',
};

$circumference = 2 * M_PI * 54; // r=54
$offset = $circumference - ($score / 100) * $circumference;
@endphp

<div class="{{ $ringColor['bg'] }} {{ $ringColor['border'] }} border rounded-xl overflow-hidden">

    <div class="px-5 py-4 border-b {{ $ringColor['border'] }} flex items-center gap-2">
        <svg class="w-5 h-5 {{ $ringColor['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <h2 class="text-sm font-semibold text-gray-800">آمادگی بازیابی فاجعه</h2>
        <span class="mr-auto px-2.5 py-0.5 rounded-full text-xs font-bold {{ $ringColor['badge'] }}">{{ $grade }}</span>
    </div>

    <div class="p-5">
        <div class="flex flex-col md:flex-row items-center gap-6">

            {{-- Ring gauge --}}
            <div class="shrink-0 relative flex items-center justify-center" style="width:130px;height:130px">
                <svg width="130" height="130" viewBox="0 0 130 130" class="-rotate-90">
                    <circle cx="65" cy="65" r="54" fill="none" stroke="#e5e7eb" stroke-width="12"/>
                    <circle cx="65" cy="65" r="54" fill="none"
                            stroke="{{ $ringColor['stroke'] }}"
                            stroke-width="12"
                            stroke-linecap="round"
                            stroke-dasharray="{{ number_format($circumference, 2) }}"
                            stroke-dashoffset="{{ number_format($offset, 2) }}"
                            style="transition: stroke-dashoffset 0.8s ease-in-out"/>
                </svg>
                <div class="absolute text-center">
                    <div class="text-3xl font-black {{ $ringColor['text'] }}">{{ $score }}%</div>
                    <div class="text-xs text-gray-500 mt-0.5">از ۱۰۰</div>
                </div>
            </div>

            {{-- Component breakdown --}}
            <div class="flex-1 w-full space-y-2">
                @foreach($components as $key => $comp)
                <div class="flex items-center gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-700 truncate">{{ $comp['label'] }}</span>
                            <span class="text-xs font-semibold text-gray-500 shrink-0 mr-2">
                                {{ $comp['score'] }}/{{ $comp['max'] }}
                            </span>
                        </div>
                        <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                            @php $pct = $comp['max'] > 0 ? round(($comp['score'] / $comp['max']) * 100) : 0; @endphp
                            <div class="h-full rounded-full transition-all duration-500
                                {{ $pct >= 80 ? 'bg-green-500' : ($pct >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                                 style="width: {{ $pct }}%"></div>
                        </div>
                    </div>
                    <span class="shrink-0 px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusColor($comp['status']) }}">
                        {{ $comp['status'] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Issues list --}}
        @if(!empty($issues))
        <div class="mt-4 pt-4 border-t border-gray-200 space-y-1">
            <p class="text-xs font-semibold text-gray-600 mb-2">موارد نیاز به توجه:</p>
            @foreach($issues as $issue)
            <div class="flex items-start gap-2 text-xs text-red-700">
                <svg class="w-3.5 h-3.5 shrink-0 mt-0.5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ $issue }}
            </div>
            @endforeach
        </div>
        @else
        <div class="mt-4 pt-4 border-t {{ $ringColor['border'] }} flex items-center gap-2 text-xs text-green-700">
            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            همه مؤلفه‌های بحرانی در وضعیت مطلوب هستند
        </div>
        @endif

        {{-- Run Simulation CTA --}}
        <div class="mt-4 flex items-center justify-between gap-3">
            <a href="{{ route('admin.disaster-recovery.center.simulations') }}"
               class="text-xs text-blue-600 hover:underline">
                @if($latestSimulation)
                    آخرین شبیه‌سازی: {{ $latestSimulation->created_at->diffForHumans() }}
                    — <span class="font-semibold {{ $latestSimulation->isPass() ? 'text-green-600' : 'text-red-600' }}">{{ $latestSimulation->result }}</span>
                @else
                    هیچ شبیه‌سازی‌ای انجام نشده
                @endif
            </a>

            <button x-data x-on:click="$dispatch('run-dr-simulation')"
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs hover:bg-indigo-700 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                اجرای شبیه‌سازی
            </button>
        </div>
    </div>
</div>

{{-- Simulation runner modal --}}
<div x-data="drSimulationRunner()" x-on:run-dr-simulation.window="run()" class="relative">
    <template x-if="running || result">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" x-on:click.self="result = null">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" x-on:click.stop>
                <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-800">شبیه‌سازی بازیابی فاجعه</h3>
                    <span class="mr-auto text-xs text-gray-400">غیرمخرب — فقط بررسی</span>
                </div>

                <div class="p-6 space-y-4">

                    <template x-if="running">
                        <div class="flex flex-col items-center gap-4 py-4">
                            <svg class="w-10 h-10 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            <p class="text-sm text-gray-600">در حال بررسی وضعیت مؤلفه‌ها...</p>
                        </div>
                    </template>

                    <template x-if="!running && result">
                        <div class="space-y-4">
                            {{-- Pass/Fail verdict --}}
                            <div class="text-center py-3">
                                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold"
                                     :class="result.result === 'PASS' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                    <svg x-show="result.result === 'PASS'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <svg x-show="result.result !== 'PASS'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <span x-text="result.result"></span>
                                </div>
                                <p class="text-2xl font-black mt-2" :class="result.score >= 90 ? 'text-green-600' : (result.score >= 75 ? 'text-blue-600' : (result.score >= 50 ? 'text-amber-600' : 'text-red-600'))"
                                   x-text="result.score + '%'"></p>
                            </div>

                            {{-- Key metrics --}}
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <dt class="text-xs text-gray-500">RPO تخمینی</dt>
                                    <dd class="font-semibold text-gray-800 mt-0.5" x-text="result.estimated_rpo"></dd>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <dt class="text-xs text-gray-500">زمان بازیابی تخمینی</dt>
                                    <dd class="font-semibold text-gray-800 mt-0.5" x-text="result.estimated_restore_time"></dd>
                                </div>
                            </dl>

                            {{-- Actions --}}
                            <div class="flex items-center gap-3 pt-2">
                                <a :href="result.history_url"
                                   class="flex-1 text-center px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition">
                                    مشاهده تاریخچه
                                </a>
                                <button x-on:click="result = null"
                                        class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">
                                    بستن
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="!running && error">
                        <div class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-3" x-text="error"></div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
function drSimulationRunner() {
    return {
        running: false,
        result:  null,
        error:   null,

        run() {
            this.running = true;
            this.result  = null;
            this.error   = null;

            fetch('{{ route('admin.disaster-recovery.center.simulations.run') }}', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({}),
            })
            .then(r => r.json())
            .then(data => {
                if (data.error) { this.error = data.error; } else { this.result = data; }
            })
            .catch(() => { this.error = 'خطا در ارتباط با سرور'; })
            .finally(() => { this.running = false; });
        }
    };
}
</script>
@endpush
