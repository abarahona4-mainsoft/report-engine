<?php

return [

    'default' => 'instana',

    'sources' => [

        'instana' => [
            'base_url'  => env('INSTANA_BASE_URL'),
            'api_token' => env('INSTANA_API_TOKEN'),
            'timeout'   => 30,
            'retry'     => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

    ],

];