<?php

namespace App\Models;

class Status {
    public const UNPROCESSED = 0;
    public const PROCESSED = 1;
    public const PROCESSING = 2;
    public const FAILED = 3;
    public const WAITING = 4;

    public static $status = [
        '0' => 'unprocessed',
        '1' => 'processed',
        '2' => 'processing',
        '3' => 'failed',
        '4' => 'waiting'
    ];
}
