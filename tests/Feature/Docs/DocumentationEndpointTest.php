<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DocumentationEndpointTest extends TestCase
{
    public function testGetDocumentationUiReturnsOkWithScalarViewer(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('@scalar/api-reference', $content);
        $this->assertStringContainsString('/docs/openapi.yaml', $content);
    }

    public function testGetOpenApiSpecReturnsOkWithYamlContent(): void
    {
        $response = $this->get('/docs/openapi.yaml');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/yaml', (string) $response->headers->get('Content-Type'));
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('openapi: 3.0.3', $content);
        $this->assertStringContainsString('/reset', $content);
        $this->assertStringContainsString('/balance', $content);
        $this->assertStringContainsString('/event', $content);
    }

    public function testGetOpenApiSpecReturnsNotFoundWhenFileDoesNotExist(): void
    {
        $realPath = base_path('docs/openapi.yaml');
        $backupPath = base_path('docs/openapi.yaml.bak');

        $hadOriginalFile = File::exists($realPath);
        if ($hadOriginalFile) {
            File::move($realPath, $backupPath);
        }

        try {
            $response = $this->get('/docs/openapi.yaml');
            $response->assertStatus(404);
        } finally {
            if ($hadOriginalFile && File::exists($backupPath)) {
                File::move($backupPath, $realPath);
            }
        }
    }
}
