<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_local_frontend_loopback_origins_are_allowed(): void
    {
        foreach (['http://localhost:5173', 'http://127.0.0.1:5173'] as $origin) {
            $this->withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => 'POST',
                'Access-Control-Request-Headers' => 'content-type,x-requested-with',
            ])
                ->options('/api/login')
                ->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', $origin);
        }
    }
}