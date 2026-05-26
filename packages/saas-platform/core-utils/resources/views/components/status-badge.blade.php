@props([
    'status' => '',
    'map'    => [],   // custom mapping override
])
{{-- Generic status badge — module-status-badge for module-specific statuses --}}
@include('core-ui::components.module-status-badge', ['status' => $status, 'map' => $map])
