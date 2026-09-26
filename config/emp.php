<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RSVP links
    |--------------------------------------------------------------------------
    */

    'rsvp_expiry_days' => (int) env('EMP_RSVP_EXPIRY_DAYS', 7),

    // Most WhatsApp invitations / reminders one guest can get per event (failed
    // messages don't count). Staff can override them per event.
    'max_invitations_per_guest' => (int) env('EMP_MAX_INVITATIONS_PER_GUEST', 3),
    'max_reminders_per_guest' => (int) env('EMP_MAX_REMINDERS_PER_GUEST', 3),

    /*
    |--------------------------------------------------------------------------
    | Invitation templates & media
    |--------------------------------------------------------------------------
    |
    | template_disk  template packages (code + the template's own assets).
    |                Must be publicly readable: guests' browsers load assets.
    | media_disk     files clients upload into template media slots (public).
    | render_disk    generated invitation HTML per event (private).
    |
    | In production all three can point at one S3/R2 bucket (different prefixes).
    |
    */

    'template_disk' => env('EMP_TEMPLATE_DISK', 'public'),

    'media_disk' => env('EMP_MEDIA_DISK', 'public'),

    'render_disk' => env('EMP_RENDER_DISK', 'local'),

    'default_currency' => env('EMP_DEFAULT_CURRENCY', 'LKR'),

    'media' => [
        'image' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max_kb' => (int) env('EMP_MEDIA_IMAGE_MAX_KB', 5 * 1024),
        ],
        'video' => [
            'mimetypes' => ['video/mp4', 'video/webm'],
            'max_kb' => (int) env('EMP_MEDIA_VIDEO_MAX_KB', 50 * 1024),
        ],
        'audio' => [
            'mimetypes' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/aac'],
            'max_kb' => (int) env('EMP_MEDIA_AUDIO_MAX_KB', 10 * 1024),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Client preview links
    |--------------------------------------------------------------------------
    */

    'preview_link_minutes' => (int) env('EMP_PREVIEW_LINK_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'per_page' => 25,

];
