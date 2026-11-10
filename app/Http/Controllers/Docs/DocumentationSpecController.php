<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

final class DocumentationSpecController
{
    public function __invoke(): Response
    {
        $path = base_path('docs/openapi.yaml');

        if (! File::exists($path)) {
            abort(404, 'OpenAPI specification not found');
        }

        $content = File::get($path);

        return response($content, 200, [
            'Content-Type' => 'text/yaml; charset=UTF-8',
        ]);
    }
}
