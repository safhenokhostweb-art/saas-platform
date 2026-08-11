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

    // دیسک آپلود (uploads + avatars + theme media + sitemap). در VPS: public/uploads
    // در Object Storage: arvan یا s3 یا minio.
    // MEDIA_DISK نام جدید و کانونیک است؛ UPLOAD_DISK به‌عنوان fallback قدیمی حفظ شده
    // تا env فعلی هیچ سروری نشکند.
    'upload_disk' => env('MEDIA_DISK', env('UPLOAD_DISK', 'public')),

    // دیسک پیوست‌های خصوصی (Attachment) — هرگز web-accessible نیست.
    'media_private_disk' => env('MEDIA_PRIVATE_DISK', 'private'),

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

        // ARVAN: Offsite backup disk (ArvanCloud S3-compatible object storage)
        'arvan_backup' => [
            'driver'                  => 's3',
            'key'                     => env('ARVAN_ACCESS_KEY'),
            'secret'                  => env('ARVAN_SECRET_KEY'),
            'region'                  => env('ARVAN_REGION', 'ir-thr-at1'),
            'bucket'                  => env('ARVAN_BUCKET', 'safhenokhost-backups'),
            'endpoint'                => env('ARVAN_ENDPOINT'),
            'url'                     => env('ARVAN_URL'),
            'use_path_style_endpoint' => true,
            'throw'                   => false,
            'visibility'              => 'private',
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

        // MEDIA-1: Arvan Object Storage — public-visibility media (avatars,
        // theme assets, sitemap). Select via MEDIA_DISK=arvan. Uses its own
        // bucket/prefix (distinct from arvan_backup) so media and backups
        // never mix in the same namespace.
        'arvan' => [
            'driver'                  => 's3',
            'key'                     => env('ARVAN_ACCESS_KEY'),
            'secret'                  => env('ARVAN_SECRET_KEY'),
            'region'                  => env('ARVAN_REGION', 'ir-thr-at1'),
            'bucket'                  => env('ARVAN_MEDIA_BUCKET', env('ARVAN_BUCKET', 'safhenokhost-media')),
            'root'                    => env('ARVAN_MEDIA_PREFIX', 'media'),
            'endpoint'                => env('ARVAN_ENDPOINT'),
            'url'                     => env('ARVAN_MEDIA_URL', env('ARVAN_URL')),
            'use_path_style_endpoint' => true,
            'throw'                   => false,
            'visibility'              => 'public',
        ],

        // MEDIA-1: Arvan Object Storage — private-visibility media (tenant
        // attachments, app releases). Select via MEDIA_PRIVATE_DISK=arvan_private.
        'arvan_private' => [
            'driver'                  => 's3',
            'key'                     => env('ARVAN_ACCESS_KEY'),
            'secret'                  => env('ARVAN_SECRET_KEY'),
            'region'                  => env('ARVAN_REGION', 'ir-thr-at1'),
            'bucket'                  => env('ARVAN_MEDIA_BUCKET', env('ARVAN_BUCKET', 'safhenokhost-media')),
            'root'                    => env('ARVAN_MEDIA_PRIVATE_PREFIX', 'media-private'),
            'endpoint'                => env('ARVAN_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw'                   => false,
            'visibility'              => 'private',
        ],

        // MEDIA-1: plain S3 — private-visibility counterpart to the existing
        // 's3' disk, for platforms that use AWS S3 instead of Arvan.
        's3_private' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'root'                    => env('AWS_PRIVATE_PREFIX', 'private'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'visibility'              => 'private',
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
