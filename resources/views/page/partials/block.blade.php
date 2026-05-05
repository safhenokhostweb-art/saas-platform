@php
    $type = $block['type'] ?? 'paragraph';
    $data = $block['data'] ?? [];
@endphp
@switch($type)
    @case('heading')
        @php $level = min(3, max(1, (int)($data['level'] ?? 2))); $text = e($data['text'] ?? ''); @endphp
        @if($level === 1)<h1 class="text-2xl font-bold text-gray-900 mt-6 mb-2">{{ $text }}</h1>
        @elseif($level === 3)<h3 class="text-lg font-bold text-gray-900 mt-6 mb-2">{{ $text }}</h3>
        @else<h2 class="text-xl font-bold text-gray-900 mt-6 mb-2">{{ $text }}</h2>
        @endif
        @break
    @case('paragraph')
        <p class="mb-4 text-gray-700 leading-relaxed">{{ nl2br(e($data['text'] ?? '')) }}</p>
        @break
    @case('rich_text')
        <div class="page-block-rich my-4 prose prose-lg max-w-none">
            {!! $data['html'] ?? '' !!}
        </div>
        @break
    @case('hero')
        @php
            $title = e($data['title'] ?? '');
            $subtitle = e($data['subtitle'] ?? '');
            $btnText = e($data['button_text'] ?? '');
            $btnUrl = e($data['button_url'] ?? '#');
            $bg = e($data['bg_color'] ?? '#4f46e5');
        @endphp
        <section class="my-8 rounded-2xl overflow-hidden text-white py-12 px-6 md:py-16 md:px-12 text-center" style="background: {{ $bg }};">
            @if($title)<h1 class="text-3xl md:text-4xl font-bold mb-4">{{ $title }}</h1>@endif
            @if($subtitle)<p class="text-lg opacity-95 max-w-2xl mx-auto mb-6">{{ $subtitle }}</p>@endif
            @if($btnText)<a href="{{ $btnUrl }}" class="inline-block bg-white text-gray-900 font-semibold px-6 py-3 rounded-xl hover:opacity-90 transition">{{ $btnText }}</a>@endif
        </section>
        @break
    @case('features')
        @php
            $ftitle = e($data['title'] ?? '');
            $f1t = e($data['f1_title'] ?? ''); $f1c = e($data['f1_text'] ?? '');
            $f2t = e($data['f2_title'] ?? ''); $f2c = e($data['f2_text'] ?? '');
            $f3t = e($data['f3_title'] ?? ''); $f3c = e($data['f3_text'] ?? '');
        @endphp
        <section class="my-10">
            @if($ftitle)<h2 class="text-2xl font-bold text-gray-900 text-center mb-8">{{ $ftitle }}</h2>@endif
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-100"><h3 class="font-bold text-gray-900 mb-2">{{ $f1t }}</h3><p class="text-gray-600 text-sm">{{ $f1c }}</p></div>
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-100"><h3 class="font-bold text-gray-900 mb-2">{{ $f2t }}</h3><p class="text-gray-600 text-sm">{{ $f2c }}</p></div>
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-100"><h3 class="font-bold text-gray-900 mb-2">{{ $f3t }}</h3><p class="text-gray-600 text-sm">{{ $f3c }}</p></div>
            </div>
        </section>
        @break
    @case('cta')
        @php
            $ctaTitle = e($data['title'] ?? '');
            $ctaSub = e($data['subtitle'] ?? '');
            $ctaBtn = e($data['button_text'] ?? '');
            $ctaUrl = e($data['button_url'] ?? '#');
            $ctaBg = e($data['bg_color'] ?? '#6366f1');
        @endphp
        <section class="my-10 rounded-2xl overflow-hidden text-white py-10 px-6 text-center" style="background: {{ $ctaBg }};">
            @if($ctaTitle)<h2 class="text-2xl font-bold mb-2">{{ $ctaTitle }}</h2>@endif
            @if($ctaSub)<p class="opacity-95 mb-4">{{ $ctaSub }}</p>@endif
            @if($ctaBtn)<a href="{{ $ctaUrl }}" class="inline-block bg-white text-gray-900 font-semibold px-5 py-2.5 rounded-lg hover:opacity-90 transition">{{ $ctaBtn }}</a>@endif
        </section>
        @break
    @case('columns')
        @php $col1 = $data['col1'] ?? ''; $col2 = $data['col2'] ?? ''; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
            <div class="text-gray-700">{{ nl2br(e($col1)) }}</div>
            <div class="text-gray-700">{{ nl2br(e($col2)) }}</div>
        </div>
        @break
    @case('image')
        @php
            $url = trim((string)($data['url'] ?? $data['src'] ?? ''));
            if ($url && !preg_match('#^https?://#i', $url)) {
                if (preg_match('#^www\.#i', $url) || preg_match('#\.(com|ir|net|org|org\.ir|co)#i', $url)) {
                    $url = 'https://' . ltrim($url, '/');
                } else {
                    $path = \App\Helpers\PlatformSettings::normalizeStoragePath($url);
                    $url = $path !== '' ? ('/storage/' . $path) : ('/' . ltrim($url, '/'));
                }
            }
            $alt = $data['alt'] ?? '';
            $caption = $data['caption'] ?? '';
            $align = $data['align'] ?? 'center';
            $figClass = 'my-6 ';
            if ($align === 'left') $figClass .= 'float-left mr-4 mb-2 max-w-xs';
            elseif ($align === 'right') $figClass .= 'float-right ml-4 mb-2 max-w-xs';
            elseif ($align === 'full') $figClass .= 'w-full';
            else $figClass .= 'max-w-2xl mx-auto';
        @endphp
        @if($url)
            <figure class="{{ $figClass }}">
                <img src="{{ $url }}" alt="{{ e($alt) }}" class="rounded-lg w-full shadow {{ $align === 'full' ? '' : '' }}" loading="lazy" referrerpolicy="no-referrer">
                @if($caption)
                    <figcaption class="text-sm text-gray-500 text-center mt-2">{{ e($caption) }}</figcaption>
                @endif
            </figure>
        @endif
        @break
    @case('gallery')
        @php
            $galTitle = e($data['title'] ?? '');
            $galItems = $data['items'] ?? [];
            $normalizeMediaUrl = function($u) {
                $u = trim((string)$u);
                if (!$u) return '';
                if (preg_match('#^https?://#i', $u)) return $u;
                $path = \App\Helpers\PlatformSettings::normalizeStoragePath($u);
                return $path !== '' ? ('/storage/' . $path) : ('/' . ltrim($u, '/'));
            };
        @endphp
        @if(count($galItems) > 0)
            <section class="my-10 gallery-carousel-wrap" aria-label="گالری">
                @if($galTitle)<h2 class="text-xl font-bold text-gray-900 mb-4">{{ $galTitle }}</h2>@endif
                <div class="gallery-carousel overflow-hidden rounded-xl border border-gray-200 bg-gray-100">
                    <div class="gallery-track flex overflow-x-auto snap-x snap-mandatory gap-4 p-4 scroll-smooth" style="scrollbar-width: thin;">
                        @foreach($galItems as $it)
                            @php $mediaUrl = $normalizeMediaUrl($it['url'] ?? ''); $typ = $it['type'] ?? 'image'; $cap = e($it['caption'] ?? ''); @endphp
                            @if($mediaUrl)
                                <div class="gallery-slide flex-shrink-0 w-[min(85vw,320px)] snap-center">
                                    @if($typ === 'video')
                                        <video src="{{ $mediaUrl }}" class="w-full rounded-lg shadow" controls></video>
                                    @else
                                        <img src="{{ $mediaUrl }}" alt="{{ $cap }}" class="w-full h-48 object-cover rounded-lg shadow" loading="lazy">
                                    @endif
                                    @if($cap)<p class="text-sm text-gray-500 mt-1 text-center">{{ $cap }}</p>@endif
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex justify-center gap-2 py-2 bg-gray-50">
                        <button type="button" class="gallery-prev px-3 py-1 rounded border bg-white text-sm" aria-label="قبلی">‹ قبلی</button>
                        <button type="button" class="gallery-next px-3 py-1 rounded border bg-white text-sm" aria-label="بعدی">بعدی ›</button>
                    </div>
                </div>
            </section>
        @endif
        @break
    @case('media_text')
        @php
            $mtUrl = trim((string)($data['url'] ?? ''));
            if ($mtUrl && !preg_match('#^https?://#i', $mtUrl)) {
                $path = \App\Helpers\PlatformSettings::normalizeStoragePath($mtUrl);
                $mtUrl = $path !== '' ? ('/storage/' . $path) : ('/' . ltrim($mtUrl, '/'));
            }
            $mtAlign = $data['align'] ?? 'left';
            $mtText = $data['text'] ?? '';
        @endphp
        <div class="my-6 flex flex-col {{ $mtAlign === 'right' ? 'md:flex-row-reverse' : 'md:flex-row' }} gap-4 items-start">
            @if($mtUrl)
                <div class="w-full md:w-1/3 flex-shrink-0">
                    <img src="{{ $mtUrl }}" alt="" class="rounded-lg shadow w-full" loading="lazy">
                </div>
            @endif
            <div class="flex-1 min-w-0 text-gray-700 prose prose-lg max-w-none">{{ nl2br(e($mtText)) }}</div>
        </div>
        @break
    @case('list')
        @php $items = $data['items'] ?? []; $ordered = !empty($data['ordered']); @endphp
        @if($ordered)
            <ol class="list-decimal list-inside mb-4 space-y-1 text-gray-700">
                @foreach($items as $item)
                    @if((string)$item !== '')
                        <li>{{ e($item) }}</li>
                    @endif
                @endforeach
            </ol>
        @else
            <ul class="list-disc list-inside mb-4 space-y-1 text-gray-700">
                @foreach($items as $item)
                    @if((string)$item !== '')
                        <li>{{ e($item) }}</li>
                    @endif
                @endforeach
            </ul>
        @endif
        @break
    @case('quote')
        <blockquote class="border-r-4 border-indigo-500 bg-gray-50 py-2 px-4 my-4 italic text-gray-700">
            {{ nl2br(e($data['text'] ?? '')) }}
            @if(!empty($data['author']))
                <cite class="block mt-2 text-sm not-italic text-gray-500">— {{ e($data['author']) }}</cite>
            @endif
        </blockquote>
        @break
    @case('code')
        <pre class="bg-gray-800 text-gray-100 p-4 rounded-lg overflow-x-auto my-4 text-sm"><code>{{ e($data['code'] ?? '') }}</code></pre>
        @break
    @case('divider')
        <hr class="my-6 border-gray-200">
        @break
    @case('spacer')
        @php $size = $data['size'] ?? 'medium'; $class = $size === 'large' ? 'my-12' : ($size === 'small' ? 'my-4' : 'my-8'); @endphp
        <div class="{{ $class }}"></div>
        @break
    @case('html')
        <div class="page-block-html my-4">
            {!! $data['html'] ?? '' !!}
        </div>
        @break
    @default
        <p class="mb-4 text-gray-700">{{ e($data['text'] ?? '') }}</p>
@endswitch
