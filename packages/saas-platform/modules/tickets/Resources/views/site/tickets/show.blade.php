@extends(config('tickets.layouts.site', 'layouts.app'))

@section('title', $ticket->subject)

@section('content')
@tenant
<div class="p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('tickets.site.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
            <h1 class="text-xl font-bold text-gray-800">{{ $ticket->subject }}</h1>
        </div>
        <div class="flex gap-2">
            @include('tickets::components.status-badge', ['status' => $ticket->status])
            @include('tickets::components.priority-badge', ['priority' => $ticket->priority])
        </div>
    </div>

    {{-- Messages Thread --}}
    <div class="space-y-4 mb-6">
        {{-- Original ticket --}}
        <div class="bg-blue-50 rounded-xl p-5">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 rounded-full bg-blue-200 text-blue-700 flex items-center justify-center text-xs font-bold">
                    {{ mb_substr(auth()->user()->name ?? 'M', 0, 1) }}
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $ticket->customer_name ?? auth()->user()->name }}</p>
                    <p class="text-xs text-gray-400">{{ $ticket->created_at->diffForHumans() }}</p>
                </div>
            </div>
            <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap">{{ $ticket->description }}</p>
        </div>

        {{-- Replies --}}
        @foreach($ticket->replies as $reply)
        @include('tickets::components.reply-item', ['reply' => $reply])
        @endforeach
    </div>

    {{-- Reply Form --}}
    @if($ticket->status !== 'closed')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">پاسخ شما</h3>
        <form method="POST" action="{{ route('tickets.site.replies.store', $ticket->id) }}">
            @csrf
            <textarea name="message" rows="4" required
                      placeholder="پیام خود را بنویسید..."
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-blue-300"></textarea>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition">
                ارسال پیام
            </button>
        </form>
    </div>
    @else
    <div class="bg-gray-50 rounded-xl border border-gray-200 p-5 text-center text-sm text-gray-500">
        این تیکت بسته شده است. برای ادامه، یک تیکت جدید ثبت کنید.
    </div>
    @endif
</div>
@endtenant
@endsection
