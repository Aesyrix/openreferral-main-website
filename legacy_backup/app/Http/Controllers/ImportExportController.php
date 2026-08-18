<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Lead;
use App\Services\LeadExportService;
use App\Services\LeadImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportExportController extends Controller
{
    public function import(Request $request, LeadImportService $importService)
    {
        $request->validate([
            'file' => 'required|file|max:15360|mimes:csv,xlsx,txt',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('imports', 'import_' . time() . '.' . $ext, 'local');

        $import = Import::create([
            'user_id' => auth()->id(),
            'type' => 'leads',
            'format' => $ext,
            'status' => 'processing',
            'error_report_path' => $path,
        ]);

        $importService->processImport($import);

        return back()->with('success', "Import processed! {$import->success_count} leads created, {$import->error_count} row errors.");
    }

    public function export(Request $request, LeadExportService $exportService)
    {
        $format = $request->get('format', 'csv');

        $query = Lead::query();

        // Scope to assigned leads if agent
        if (auth()->user()->isAgent()) {
            $query->where('assigned_agent_id', auth()->id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function($b) use ($q) {
                $b->where('public_ref', 'like', "%{$q}%")
                  ->orWhere('first_name', 'like', "%{$q}%")
                  ->orWhere('last_name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('city', 'like', "%{$q}%");
            });
        }

        $leads = $query->latest()->get();

        $filePath = $exportService->exportLeads($leads, $format);

        return response()->download($filePath)->deleteFileAfterSend(true);
    }
}
