<?php

return [
    'enabled' => false,
    'routes_enabled' => false,
    'admin_ui_enabled' => false,
    'runtime_api_enabled' => false,
    'publishing_enabled' => false,
    'ai_enabled' => false,
    'customer_runtime_enabled' => false,
    'real_storage_enabled' => false,
    'limits' => [
        'max_source_bytes' => 2 * 1024 * 1024,
        'max_asset_bytes' => 5 * 1024 * 1024,
    ],
    'allowed_asset_types' => ['lottie', 'svg', 'html', 'canvas', 'web-component', 'motion-json'],
    'allowed_engines' => ['lottie', 'svg-js', 'html-css-js', 'canvas-2d', 'web-component', 'motion-json'],
    'allow_remote_dependencies' => false,
    'asset_path_prefix' => 'motion',
    'admin_middleware' => ['web'],
    'runtime_middleware' => ['api'],
];
