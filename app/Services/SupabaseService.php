<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseService
{
    protected string $url;
    protected string $publishableKey;
    protected string $secretKey;

    public function __construct()
    {
        $this->url = rtrim(config('supabase.url', env('SUPABASE_URL', '')), '/');
        $this->publishableKey = config('supabase.publishable_key', env('SUPABASE_PUBLISHABLE_KEY', ''));
        $this->secretKey = config('supabase.secret_key', env('SUPABASE_SECRET_KEY', ''));
    }

    /**
     * Test connection to Supabase REST API endpoint.
     */
    public function ping(): array
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->secretKey,
                'Authorization' => "Bearer {$this->secretKey}",
            ])->timeout(5)->get("{$this->url}/rest/v1/");

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'server' => 'Supabase PostgREST Gateway',
                'connected' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('Supabase ping failed: ' . $e->getMessage());

            return [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
                'connected' => false,
            ];
        }
    }

    /**
     * Query a Supabase PostgREST table.
     */
    public function from(string $table): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'apikey' => $this->secretKey,
            'Authorization' => "Bearer {$this->secretKey}",
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->baseUrl("{$this->url}/rest/v1/{$table}");
    }

    /**
     * Upload a file to Supabase Storage.
     */
    public function uploadFile(string $bucket, string $path, string $fileContents, string $mimeType = 'image/jpeg'): ?string
    {
        try {
            $endpoint = "{$this->url}/storage/v1/object/{$bucket}/{$path}";

            $response = Http::withHeaders([
                'apikey' => $this->secretKey,
                'Authorization' => "Bearer {$this->secretKey}",
                'Content-Type' => $mimeType,
            ])->withBody($fileContents, $mimeType)->post($endpoint);

            if ($response->successful()) {
                return "{$this->url}/storage/v1/object/public/{$bucket}/{$path}";
            }

            Log::error("Supabase storage upload failed: " . $response->body());
            return null;
        } catch (\Throwable $e) {
            Log::error("Supabase storage exception: " . $e->getMessage());
            return null;
        }
    }
}
