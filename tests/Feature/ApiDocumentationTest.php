<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    public function test_la_documentacion_esta_deshabilitada_por_defecto(): void
    {
        $this->assertFalse(config('app.api_docs_enabled'));

        $this->get(route('api.documentation'))->assertNotFound();
        $this->get(route('api.documentation.spec'))->assertNotFound();
    }

    public function test_la_documentacion_responde_cuando_esta_habilitada(): void
    {
        config(['app.api_docs_enabled' => true]);

        $this->get(route('api.documentation'))
            ->assertOk()
            ->assertSee(route('api.documentation.spec'), false);

        $this->get(route('api.documentation.spec'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');
    }
}
