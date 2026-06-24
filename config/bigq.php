<?php

return [
    'connection' => [

        'project_id' => env('GOOGLE_CLOUD_BIGQUERY_PROJECT_ID'),
        
        'key_file_path' => env('GOOGLE_CLOUD_BIGQUERY_CREDENTIALS'),
        
        'production' => [
            'choicepot' => [
                'dataset' => env('GOOGLE_CLOUD_CHOICEPOT_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_CHOICEPOT_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_CHOICEPOT_STAGING_TABLE_NAME')
                ]
            ],
            'fsa' => [
                'dataset' => env('GOOGLE_CLOUD_FSA_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_FSA_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_FSA_STAGING_TABLE_NAME')
                ],
            ]
        ],
        
        'staging' => [
            'choicepot' => [
                'dataset' => env('GOOGLE_CLOUD_CHOICEPOT_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_CHOICEPOT_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_CHOICEPOT_STAGING_TABLE_NAME')
                ]
            ],
            'fsa' => [
                'dataset' => env('GOOGLE_CLOUD_FSA_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_FSA_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_FSA_STAGING_TABLE_NAME')
                ],
            ]
        ],
        
        'local' => [
            'choicepot' => [
                'dataset' => env('GOOGLE_CLOUD_CHOICEPOT_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_CHOICEPOT_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_CHOICEPOT_STAGING_TABLE_NAME')
                ]
            ],
            'fsa' => [
                'dataset' => env('GOOGLE_CLOUD_FSA_DATASET'),
                'table_name' => [
                    'raw' => env('GOOGLE_CLOUD_FSA_TABLE_NAME'),
                    'staging' => env('GOOGLE_CLOUD_FSA_STAGING_TABLE_NAME')
                ],
            ]
        ]
    ]
];