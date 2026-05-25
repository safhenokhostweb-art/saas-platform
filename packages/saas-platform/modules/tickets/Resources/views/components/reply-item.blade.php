@php
    $isStaff  = ($reply->author_type ?? '') === 'staff';
    $bgClass  = $isStaff ? 'bg-green-50 border-green-100' : 'bg-blue-50 border-blue-100';
    $initial  = mb_substr($reply->user?->name ?? ($isStaff ? 'پشتیبانی' : 'مشتری'), 0, 1);
    $nameColor = $isStaff ? 'text-green-700' : 'text-blue-700';
    $avatarBg  = $isStaff ? 'bg-green-200 text-green-700' : 'bg-blue-200 text-blue-700';
@endphp
<div class="rounded-xl border p-4 {{ $bgClass }}">
    <div class="flex items-center gap-2 mb-2">
        <div class="w-7 h-7 rounded-full {{ $avatarBg }} flex items-center justify-center text-xs font-bold">
            {{ $initial }}
        </div>
        <div class="flex items-center gap-2">
            <p class="text-sm font-medium {{ $nameColor }}">
                {{ $reply->user?->name ?? ($isStaff ? 'پشتیبانی' : 'مشتری') }}
            </p>
            @if($isStaff)
            <span class="text-xs bg-green-200 text-green-800 px-1.5 py-0.5 rounded">پشتیبان</span>
            @endif
        </div>
        <p class="text-xs text-gray-400 mr-auto">{{ $reply->created_at->diffForHumans() }}</p>
    </div>
    <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>

    @if(!empty($reply->attachments))
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach($reply->attachments as $file)
        <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
           class="text-xs bg-white text-gray-600 border border-gray-200 px-2 py-1 rounded-full hover:bg-gray-50 transition">
            📎 {{ $file['name'] }}
        </a>
        @endforeach
    </div>
    @endif
</div>
