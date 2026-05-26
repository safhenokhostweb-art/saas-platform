@php
$isStaff    = ($reply->author_type ?? '') === 'staff';
$authorName = $isStaff
    ? ($reply->user?->name ?? 'پشتیبان')
    : ($reply->customer_name ?? 'مشتری');
$initial    = mb_substr($authorName, 0, 1);
$bgColor    = $isStaff ? '#dcf8c6' : '#e3f2fd';
$avatarColor = $isStaff ? '#10b981' : '#6366f1';
@endphp
<div class="flex items-end gap-2 max-w-[85%] mb-4 {{ $isStaff ? 'flex-row-reverse mr-auto' : '' }}">
    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0 shadow-sm"
         style="background-color: {{ $avatarColor }};">
        {{ $initial }}
    </div>
    <div>
        <div class="rounded-2xl px-4 py-2.5 shadow-sm {{ $isStaff ? 'rounded-bl-sm' : 'rounded-br-sm' }}"
             style="background-color: {{ $bgColor }}; color: #1a1a2e;">
            <p class="text-xs font-semibold mb-1 opacity-80">
                {{ $authorName }}
                @if($isStaff)
                    <span class="inline-flex items-center text-[9px] bg-green-200/50 px-1.5 py-0.5 rounded-full mr-1">پشتیبان</span>
                @endif
            </p>
            <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $reply->message }}</p>
            @if(!empty($reply->attachments) && is_array($reply->attachments))
            <div class="flex flex-wrap gap-1.5 mt-2">
                @foreach($reply->attachments as $file)
                    @if(isset($file['path']))
                    <a href="{{ asset('storage/' . $file['path']) }}" target="_blank"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] bg-white/60 hover:bg-white border border-gray-200 transition-all">
                        📎 <span class="truncate max-w-[100px]">{{ $file['name'] ?? 'فایل' }}</span>
                    </a>
                    @endif
                @endforeach
            </div>
            @endif
        </div>
        <span class="text-[10px] text-gray-400 mt-1 px-1 {{ $isStaff ? 'text-left block' : '' }}">
            {{ \App\Helpers\Jalali::formatFa($reply->created_at, 'H:i') }}
        </span>
    </div>
</div>
