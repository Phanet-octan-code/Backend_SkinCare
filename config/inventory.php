<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configured Store Locations and Storage Branches
    |--------------------------------------------------------------------------
    |
    | Inter-branch and warehouse stock transfers occur between these validated
    | physical and retail locations.
    |
    */
    'branches' => [
        'Central Warehouse' => 'Central Warehouse & Distribution Hub',
        'Downtown Flagship' => 'Flagship Boutique (Downtown)',
        'Spa Suite' => 'Aesthetic Spa & Treatment Suite',
        'Westfield Pop-up' => 'Westfield Mall Retail Pop-Up',
        'Airport Duty-Free' => 'International Airport Duty-Free Store',
    ],

    'default_location' => 'Central Warehouse',

    /*
    |--------------------------------------------------------------------------
    | Default Low Stock Reorder Threshold
    |--------------------------------------------------------------------------
    */
    'default_low_stock_threshold' => 10,
];
