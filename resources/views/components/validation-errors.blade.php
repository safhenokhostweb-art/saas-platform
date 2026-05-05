@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-100 border border-red-300 text-red-800 px-4 py-3">
        <ul class="list-disc pr-5 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
