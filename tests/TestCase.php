<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Las pruebas que hereden y no quieran usuario API pueden sobrescribir a false.
     */
    protected function shouldAuthenticateApi(): bool
    {
        return true;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAuthenticateApi()) {
            Sanctum::actingAs(User::factory()->create());
        }
    }
}
