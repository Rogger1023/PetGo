<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_application_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson([
                'app' => 'PetGo',
                'status' => 'ok',
            ]);
    }
}
