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
        $this->get('/login')->assertOk()->assertSee('serviconli-vue-root', false);
    }

    public function test_mis_afiliados_page_returns_ok(): void
    {
        $this->get('/mis-afiliados')->assertOk()->assertSee('serviconli-vue-root', false);
    }

    public function test_ficha_page_returns_ok(): void
    {
        $this->get('/afiliados/1/ficha')->assertOk()->assertSee('data-affiliate-id="1"', false);
    }

    public function test_aporte_individual_page_returns_ok(): void
    {
        $this->get('/afiliados/1/aporte')->assertOk()->assertSee('data-affiliate-id="1"', false);
    }

    public function test_liquidacion_lotes_page_returns_ok(): void
    {
        $this->get('/liquidacion-lotes')->assertOk()->assertSee('serviconli-vue-root', false);
    }
}
