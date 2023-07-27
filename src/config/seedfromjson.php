<?php

return [
    // Name of the storage disk pointing to the folder with the JSON files
    'STORAGE_FOLDER_NAME' => env('SEEDFROMJSON_DISK_NAME','seed_content'),

    // Enable Quick-Seeding (will only import the first x num of records when seeding)
    'ENABLE_QUICK_SEEDING' => env('SEEDFROMJSON_QUICK_SEEDING', config('app.env') === 'local'),

    // Number of records to seed during Quick-Seeding
    'NUM_QUICK_RECORDS' => env('SEEDFROMJSON_NUM_QUICK_RECORDS',100),

    // Disable all FK CONSTRAINTS
    'DISABLE_ALL_FK_CONSTRAINTS' => env('SEEDFROMJSON_DISABLE_ALL_FK_CONSTRAINTS', true),
];
