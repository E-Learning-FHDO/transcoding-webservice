<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),
        'threads' => env('FFMPEG_THREADS', 12),
        'debug' => env('FFMPEG_DEBUG', false),
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
         'debug' => env('FFPROBE_DEBUG', false),
    ],

    'timeout' => env('FFMPEG_TIMEOUT', 3600),
];
