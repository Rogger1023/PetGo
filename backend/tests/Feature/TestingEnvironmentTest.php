<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingEnvironmentTest extends TestCase
{
    public function test_testing_environment_uses_sqlite_in_memory(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );
    }
}
