<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiconliWebUiTest extends TestCase
{
    protected function shouldAuthenticateApi(): bool
    {
        return false;
    }

    public function test_login_page_returns_ok(): void
    {
        $this->get('/login')->assertOk()->assertSee('login-form', false);
    }

    public function test_mis_afiliados_page_returns_ok(): void
    {
        $this->get('/mis-afiliados')->assertOk()->assertSee('affiliates-tbody', false);
    }

    public function test_ficha_page_returns_ok(): void
    {
        $this->get('/afiliados/1/ficha')->assertOk()->assertSee('ficha-root', false);
    }
}
