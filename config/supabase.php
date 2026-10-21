<?php

return [
    'url' => env('SUPABASE_URL'),
    'publishable_key' => env('SUPABASE_PUBLISHABLE_KEY'),
    'secret_key' => env('SUPABASE_SECRET_KEY'),
    'jwks_url' => env('SUPABASE_JWKS_URL'),
    'storage_bucket' => env('SUPABASE_STORAGE_BUCKET', 'skincare-products'),
];
