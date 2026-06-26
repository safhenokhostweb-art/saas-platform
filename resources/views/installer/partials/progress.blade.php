{{-- Progress track for wizard steps --}}
<div class="progress-track" style="margin-bottom:40px;">
    @foreach($steps as $i => $s)
        @php
            $isDone   = in_array($s, $completedSteps);
            $isActive = ($s === $step);
            $cls      = $isDone ? 'done' : ($isActive ? 'active' : '');
        @endphp
        <div class="progress-step {{ $cls }}">
            <div class="progress-dot">
                @if($isDone)
                    ✓
                @else
                    {{ $i + 1 }}
                @endif
            </div>
            <div class="progress-label">{{ $stepLabels[$s] ?? $s }}</div>
        </div>
    @endforeach
</div>
