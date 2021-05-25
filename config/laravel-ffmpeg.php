<?php

return [
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', 'ffmpeg'),
        'threads'  => env('FFMPEG_THREADS', 12),
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', 'ffprobe'),
    ],

    'timeout' => env('FFMPEG_TIMEOUT', 3600),

    'enable_logging' => env('FFPROBE_DEBUG', false),

    'set_command_and_error_output_on_exception' => true,

    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),
];
