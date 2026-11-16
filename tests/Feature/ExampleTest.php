<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_frontend_routes_serve_the_build_without_intercepting_backend_routes(): void
    {
        $public = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gic-frontend-test-'.bin2hex(random_bytes(8));
        $frontend = $public.DIRECTORY_SEPARATOR.'frontend';
        mkdir($frontend, 0755, true);
        file_put_contents($frontend.DIRECTORY_SEPARATOR.'index.html', '<!doctype html><title>GIC</title>');

        try {
            $this->app->usePublicPath($public);
            foreach (['/', '/auth/login', '/auth/redefinir-senha?token=example', '/admin/moradores'] as $path) {
                $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-cache, public');
            }
            $this->getJson('/api/rota-inexistente')->assertNotFound();
            $this->get('/frontend/assets/inexistente.js')->assertNotFound();
            $this->get('/up')->assertOk();
        } finally {
            unlink($frontend.DIRECTORY_SEPARATOR.'index.html');
            rmdir($frontend);
            rmdir($public);
        }
    }

    public function test_missing_frontend_build_reports_service_unavailable(): void
    {
        $this->app->usePublicPath(sys_get_temp_dir().DIRECTORY_SEPARATOR.'gic-frontend-missing-'.bin2hex(random_bytes(8)));

        $this->get('/admin/moradores')->assertStatus(503);
    }
}
