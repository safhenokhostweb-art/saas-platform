@php($manifestJson = json_encode($manifest, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT))
<div {{ $attributes->merge([
    'class' => 'motion-graphics-runtime',
    'data-motion-name' => $manifest['slug'],
    'data-motion-version' => $manifest['version'],
]) }}>
    <script type='application/json' data-motion-manifest>{!! $manifestJson !!}</script>
    @foreach($manifest['assets'] as $asset)
        <link rel='preload' href='{{ $asset['url'] }}' as='fetch' crossorigin='anonymous'>
    @endforeach
</div>
