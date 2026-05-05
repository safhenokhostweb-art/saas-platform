{{-- Success --}}
@if(session('success'))
    <div class="mb-4 rounded-lg bg-green-100 border border-green-300 text-green-800 px-4 py-3">
        {{ session('success') }}
    </div>
@endif

{{-- Error --}}
@if(session('error'))
    <div class="mb-4 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
        {{ session('error') }}
    </div>
@endif

{{-- Warning --}}
@if(session('warning'))
    <div class="mb-4 rounded-lg bg-yellow-100 border border-yellow-300 text-yellow-800 px-4 py-3">
        {{ session('warning') }}
    </div>
@endif

{{-- Info --}}
@if(session('info'))
    <div class="mb-4 rounded-lg bg-blue-100 border border-blue-300 text-blue-800 px-4 py-3">
        {{ session('info') }}
    </div>
@endif
