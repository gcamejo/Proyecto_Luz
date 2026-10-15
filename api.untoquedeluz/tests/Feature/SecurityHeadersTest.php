<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_sent_in_production()
    {
        app()->detectEnvironment(function () {
            return 'production';
        });

        $this->get('/')
            ->assertHeader('Strict-Transport-Security', 'max-age=300')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
