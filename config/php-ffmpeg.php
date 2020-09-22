<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),
        'threads' => 12,
        'debug' => env('FFMPEG_DEBUG', false),
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
         'debug' => env('FFPROBE_DEBUG', false),
    ],

    'timeout' => 3600,
];
