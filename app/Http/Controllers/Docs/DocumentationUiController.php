<?php

declare(strict_types=1);

namespace App\Http\Controllers\Docs;

use Illuminate\Contracts\View\View;

final class DocumentationUiController
{
    public function __invoke(): View
    {
        return view('docs');
    }
}

