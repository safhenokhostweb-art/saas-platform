<li class="p-4 hover:bg-gray-50 flex justify-between items-center">
    <div>
        <span class="font-medium">{{ $item->title }}</span>
        @if($item->url)
            <span class="text-gray-500 text-sm block">{{ $item->url }}</span>
        @endif
        @if($item->children->isNotEmpty())
            <ul class="mr-6 mt-2 space-y-1 border-r-2 border-gray-200 pr-2">
                @foreach($item->children as $child)
                    @include('admin.menus.partials.item', ['item' => $child])
                @endforeach
            </ul>
        @endif
    </div>
    <div class="flex gap-2">
        <a href="#" class="text-indigo-600 text-sm" onclick="event.preventDefault(); document.getElementById('edit-form-{{ $item->id }}').classList.toggle('hidden');">ویرایش</a>
        <form method="POST" action="{{ route('admin.sites.menus.items.destroy', [$site, $menu, $item]) }}" class="inline" onsubmit="return confirm('حذف این آیتم؟');">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-red-600 text-sm">حذف</button>
        </form>
    </div>
</li>
<div id="edit-form-{{ $item->id }}" class="hidden p-4 bg-gray-50 rounded mb-2">
    <form method="POST" action="{{ route('admin.sites.menus.items.update', [$site, $menu, $item]) }}">
        @csrf
        @method('PUT')
        <div class="flex flex-wrap gap-3">
            <input type="text" name="title" value="{{ $item->title }}" class="form-control flex-1 min-w-32" required>
            <input type="text" name="url" value="{{ $item->url }}" class="form-control flex-1 min-w-32">
            <select name="type" class="form-control w-32">
                @foreach(\App\Models\MenuItem::types() as $val => $label)
                    <option value="{{ $val }}" {{ $item->type === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1">
                <input type="checkbox" name="is_active" value="1" {{ $item->is_active ? 'checked' : '' }}>
                <span class="text-sm">فعال</span>
            </label>
            <button type="submit" class="btn btn-primary">ذخیره</button>
        </div>
    </form>
</div>
