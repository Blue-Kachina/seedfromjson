<?php

return [
    // folder where seed content can be found
    'STORAGE_FOLDER_NAME' => env('STORAGE_FOLDER_NAME','seed_content'),

    // number of records to seed during quick_seed operations
    'NUM_QUICK_RECORDS' => env('NUM_QUICK_RECORDS',100),
];
