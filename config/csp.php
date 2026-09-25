<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy origins
    |--------------------------------------------------------------------------
    |
    | Site pages (see ContentSecurityPolicy middleware) render invitation
    | HTML generated from templates. Media and template assets are served
    | from this app's own storage ('self'); list extra origins (S3, CDN) in
    | EMP_MEDIA_ORIGINS, space separated. Fonts come from the template.
    | Scripts are only ever this app's own bundle.
    |
    */

    'media_origins' => array_values(array_filter(explode(' ', (string) env('EMP_MEDIA_ORIGINS', '')))),

    'font_style_origins' => ['https://fonts.googleapis.com', 'https://fonts.bunny.net'],

    'font_origins' => ['https://fonts.gstatic.com', 'https://fonts.bunny.net'],

];
