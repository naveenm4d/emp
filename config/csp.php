<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy origins
    |--------------------------------------------------------------------------
    |
    | Web pages (see ContentSecurityPolicy middleware) render invitation
    | HTML generated from templates. Media and template assets are served
    | from this app's own storage ('self'); list extra origins (S3, CDN) in
    | EMP_MEDIA_ORIGINS, space separated. Fonts come from the template.
    |
    | Scripts: this app's bundle plus the template disk (templates' js/ files,
    | uploaded by staff). List CDNs that template JS loads or calls in
    | EMP_SCRIPT_ORIGINS, space separated.
    |
    */

    'script_origins' => array_values(array_filter(explode(' ', (string) env('EMP_SCRIPT_ORIGINS', '')))),

    'media_origins' => array_values(array_filter(explode(' ', (string) env('EMP_MEDIA_ORIGINS', '')))),

    'font_style_origins' => ['https://fonts.googleapis.com', 'https://fonts.bunny.net'],

    'font_origins' => ['https://fonts.gstatic.com', 'https://fonts.bunny.net'],

];
