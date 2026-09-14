<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_swagger_page_is_accessible(): void
    {
        $this->get('/swagger')->assertOk()->assertSee('SwaggerUIBundle', false);
    }

    public function test_annotations_generate_all_api_routes_with_matching_authentication(): void
    {
        $this->artisan('swagger:generate')->assertSuccessful();
        $document = json_decode(file_get_contents(public_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('3.0.3', $document['openapi']);
        $expected = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $path = '/'.$route->uri();
                $verb = strtolower($method);
                $expected[] = $verb.' '.$path;
                $operation = $document['paths'][$path][$verb];
                $protected = in_array('auth:sanctum', $route->gatherMiddleware(), true);
                $this->assertSame($protected, ! empty($operation['security']), $path);
            }
        }

        $actual = [];
        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $actual[] = $method.' '.$path;
            }
        }

        $this->assertEqualsCanonicalizing($expected, $actual);
    }
}
