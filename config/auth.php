<?php

use App\Domains\Client\Models\ClientUser;
use App\Domains\Staff\Models\StaffMember;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | EMP has two independent authenticated audiences:
    |  - client: users of client accounts who create and manage events (/app);
    |    an account (Client) has several users (ClientUser), see the Client domain
    |  - staff:  EMP platform internal staff (/admin)
    |
    | Guests (event attendees) never authenticate; they use public slugs and
    | RSVP tokens against the JSON API.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'client'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'clients'),
    ],

    'guards' => [
        'client' => [
            'driver' => 'session',
            'provider' => 'clients',
        ],

        'staff' => [
            'driver' => 'session',
            'provider' => 'staff_members',
        ],
    ],

    'providers' => [
        'clients' => [
            'driver' => 'eloquent',
            'model' => ClientUser::class,
        ],

        'staff_members' => [
            'driver' => 'eloquent',
            'model' => StaffMember::class,
        ],
    ],

    'passwords' => [
        'clients' => [
            'provider' => 'clients',
            'table' => 'client_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        // Invitations to join a client account: the link sets the user's password.
        'client_invitations' => [
            'provider' => 'clients',
            'table' => 'client_invitation_tokens',
            'expire' => 10080,
            'throttle' => 60,
        ],

        'staff_members' => [
            'provider' => 'staff_members',
            'table' => 'staff_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
