<?php

return [
    /*
    | Disco donde se guardan fotos y videos. 'media' = local privado.
    | En producción puede ser 's3' (o cualquier disco compatible con S3).
    */
    'disk' => env('MEDIA_DISK', 'media'),

    // Directorio temporal (siempre local) donde se ensamblan las subidas por partes.
    'chunk_dir' => storage_path('app/private/chunks'),

    'chunk_size_mb' => (int) env('MEDIA_CHUNK_MB', 5),

    'limits' => [
        'image_mb' => (int) env('MEDIA_MAX_IMAGE_MB', 25),
        'video_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 800),
        'photos_per_memory' => 10,
        'videos_per_contribution' => 3,
        'message_chars' => 600,
        'letter_chars' => 20000,
        'text_chars' => 8000,
    ],

    'allowed' => [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'video' => [
            'extensions' => ['mp4', 'mov', 'm4v', 'webm', '3gp'],
            'mimes' => [
                'video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v',
                'video/3gpp', 'video/3gpp2',
            ],
        ],
        'audio' => [
            'extensions' => ['mp3', 'm4a', 'ogg'],
            'mimes' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'video/mp4'],
        ],
    ],

    // Tiempo que una subida sin asociar a una aportación permanece antes de limpiarse.
    'orphan_hours' => 48,

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL', 'admin@pedacito.local'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // Valores iniciales del evento (el seeder los usa la primera vez; luego se editan en Configuración).
    'event' => [
        'celebrant_name' => env('CELEBRANT_NAME', 'La cumpleañera'),
        'celebrant_short_name' => env('CELEBRANT_SHORT_NAME', 'Cumpleañera'),
        'age' => env('CELEBRANT_AGE') !== null ? (int) env('CELEBRANT_AGE') : null,
        'birthday_date' => env('BIRTHDAY_DATE'),
        'submissions_close_at' => env('SUBMISSIONS_CLOSE_AT'),
    ],
];
