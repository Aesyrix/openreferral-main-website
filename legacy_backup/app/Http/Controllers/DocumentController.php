<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentController extends Controller
{
    public function store(Request $request, DocumentService $service)
    {
        $request->validate([
            'file' => 'required|file|max:15360|mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png',
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'category' => 'nullable|string',
            'visibility' => 'nullable|string|in:internal,shared_with_agent',
        ]);

        $category = $request->category ?? 'other';
        $visibility = $request->visibility ?? 'shared_with_agent';

        $service->store(
            $request->file('file'),
            $request->model_type,
            (int)$request->model_id,
            auth()->user(),
            $category,
            $visibility
        );

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function download(string $uuid, DocumentService $service)
    {
        $document = Document::where('uuid', $uuid)->firstOrFail();

        // Security check via DocumentPolicy
        Gate::authorize('view', $document);

        return $service->download($document);
    }
}
