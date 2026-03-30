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

        // Sin manifest/hot las pruebas fallarían; en CI no suele ejecutarse npm run build.
        if (app()->environment('testing')
            && ! is_file(public_path('build/manifest.json'))
            && ! is_file(public_path('hot'))) {
            $this->withoutVite();
        }

        if ($this->shouldAuthenticateApi()) {
            Sanctum::actingAs(User::factory()->create());
        }
    }
}
