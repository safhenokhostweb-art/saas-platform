<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    // دیسک آپلود (uploads + avatars). در VPS: public — در S3: s3 یا minio
    'upload_disk' => env('UPLOAD_DISK', 'public'),

    // دیسک export موقت CSV. همیشه local (web-accessible نباشد)
    'export_disk' => env('EXPORT_DISK', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been set up for each driver as an example of the required values.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
        ],

        // PHASE 8.0 — explicit private disk for tenant attachments (never web-accessible)
        'private' => [
            'driver'     => 'local',
            'root'       => storage_path('app/private'),
            'throw'      => false,
            'visibility' => 'private',
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        // INFRA-2: MinIO / compatible object storage (path-style endpoint required)
        'minio' => [
            'driver'                  => 's3',
            'key'                     => env('MINIO_ACCESS_KEY_ID'),
            'secret'                  => env('MINIO_SECRET_ACCESS_KEY'),
            'region'                  => env('MINIO_REGION', 'us-east-1'),
            'bucket'                  => env('MINIO_BUCKET'),
            'endpoint'                => env('MINIO_ENDPOINT'),
            'url'                     => env('MINIO_URL'),
            'use_path_style_endpoint' => true,
            'throw'                   => false,
        ],

        // INFRA-2: Dedicated disk for temporary CSV exports (never web-accessible)
        'exports' => [
            'driver' => 'local',
            'root'   => storage_path('app/exports'),
            'throw'  => false,
        ],

        // INFRA-2: Legacy public avatar storage (web-accessible via /uploads URL)
        // Maps to public/uploads so existing avatar URLs continue to resolve.
        'uploads' => [
            'driver'     => 'local',
            'root'       => public_path('uploads'),
            'url'        => env('APP_URL') . '/uploads',
            'visibility' => 'public',
            'throw'      => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
