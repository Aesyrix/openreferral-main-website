<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\JsonResponse;

/**
 * Static app copy (About / Privacy / Terms / Help) so it can change without an
 * app release. The app ships fallback copy if this 404s.
 */
class ContentController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        abort_unless(in_array($slug, ContentPage::SLUGS, true), 404);

        $page = ContentPage::where('slug', $slug)->first();

        abort_if($page === null, 404, "No content published for '{$slug}'.");

        return response()->json([
            'slug' => $page->slug,
            'title' => $page->title,
            'intro' => $page->intro,
            'sections' => $page->sections(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ContentPage::orderBy('slug')->get()->map(fn (ContentPage $page) => [
                'slug' => $page->slug,
                'title' => $page->title,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]),
        ]);
    }
}
