<?php

namespace App\Http\Controllers;

use App\Services\WeddingShareMetadataService;
use Illuminate\Http\Response;

class WeddingShareController extends Controller
{
    public function __construct(private WeddingShareMetadataService $metadataService) {}

    public function show(string $domain): Response
    {
        $metadata = $this->metadataService->resolve($domain);

        if (! $metadata) {
            abort(404);
        }

        return response()
            ->view('wedding-share', $metadata)
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
