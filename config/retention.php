<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention windows
    |--------------------------------------------------------------------------
    |
    | Used by the App\Services\DataRetention sweep (app:purge-retention).
    | Set a value to 0 to disable that pass entirely.
    |
    */

    // Archived customers are never deleted automatically (their reports and
    // billing records stay); People → Archived → Delete permanently does it by hand.

    // Prune read in-app notifications older than this many days (housekeeping).
    'read_notification_days' => (int) env('RETENTION_NOTIFICATION_DAYS', 90),

    // Prune a tech's last-known position once it's this old (one row per tech;
    // the live map only shows the last 30 minutes).
    'agent_location_days' => (int) env('RETENTION_AGENT_LOCATION_DAYS', 7),

    // Files sent with Email people messages (the record of what was sent stays).
    'campaign_attachment_days' => (int) env('RETENTION_CAMPAIGN_ATTACHMENT_DAYS', 90),

    // Clear where a website lead was sent from (its IP) once it's this old —
    // the same window the web server keeps its logs. The lead itself stays.
    'lead_ip_days' => (int) env('RETENTION_LEAD_IP_DAYS', 90),

    // Delete assistant and website-chatbot conversations once nothing has been
    // said in them for this many days (their messages go with them).
    'chat_days' => (int) env('RETENTION_CHAT_DAYS', 45),

];
