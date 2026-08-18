<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Enums\DocumentVisibility;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function store(UploadedFile $file, string $modelType, int $modelId, User $uploader, string $category = 'other', string $visibility = 'shared_with_agent'): Document
    {
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();
        $filename = time() . '_' . Str::random(8) . '.' . $ext;

        $path = $file->storeAs('documents', $filename, 'local');

        return Document::create([
            'documentable_type' => $modelType,
            'documentable_id' => $modelId,
            'uploaded_by_id' => $uploader->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'category' => $category,
            'visibility' => $visibility,
            'virus_scan_status' => 'clean',
        ]);
    }

    public function download(Document $document)
    {
        if (!Storage::disk($document->disk)->exists($document->path)) {
            abort(404, 'File not found on storage server.');
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }
}
