<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * App copy (About / Privacy / Terms / Help) served to the app at
 * `GET /api/content/{slug}` — admin only.
 */
class ContentController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage-content');

        $pages = ContentPage::orderBy('slug')->get()->keyBy('slug');

        return view('admin.content.index', [
            'slugs' => ContentPage::SLUGS,
            'pages' => $pages,
        ]);
    }

    public function edit(string $slug): View
    {
        $this->authorize('manage-content');

        abort_unless(in_array($slug, ContentPage::SLUGS, true), 404);

        return view('admin.content.edit', [
            'slug' => $slug,
            'page' => ContentPage::where('slug', $slug)->first(),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $this->authorize('manage-content');

        abort_unless(in_array($slug, ContentPage::SLUGS, true), 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'sections' => ['required', 'array', 'min:1', 'max:40'],
            'sections.*.heading' => ['required', 'string', 'max:190'],
            'sections.*.body' => ['required', 'string', 'max:20000'],
        ]);

        $sections = collect($data['sections'])
            ->map(fn ($section) => [
                'heading' => trim($section['heading']),
                'body' => trim($section['body']),
            ])
            ->filter(fn ($section) => $section['heading'] !== '' && $section['body'] !== '')
            ->values()
            ->all();

        $page = ContentPage::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $data['title'],
                'intro' => $data['intro'] ?? null,
                'body_json' => $sections,
            ]
        );

        ActivityLogger::log('content.updated', null, 'content_page', $page->id, ['slug' => $slug]);

        return redirect()
            ->route('admin.content.index')
            ->with('success', ucfirst($slug).' page saved — the app picks it up immediately.');
    }
}
