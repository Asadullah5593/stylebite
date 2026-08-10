<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Campaign Chunking
    |--------------------------------------------------------------------------
    |
    | How many recipients one job run processes before re-queueing itself.
    | Shared hosting runs `queue:work --max-time=50` from a per-minute cron, so
    | a run must finish comfortably inside that window: keep this small enough
    | that chunk_size × per-recipient latency stays well under 50 seconds.
    |
    */

    'campaign_chunk_size' => (int) env('CAMPAIGN_CHUNK_SIZE', 200),

    /*
    |--------------------------------------------------------------------------
    | Stale Campaign Timeout
    |--------------------------------------------------------------------------
    |
    | A running campaign whose worker died (killed mid-run, deploy, fatal) is
    | considered stale after this many minutes and may be resumed.
    |
    */

    'campaign_stale_after_minutes' => (int) env('CAMPAIGN_STALE_AFTER_MINUTES', 15),

];
