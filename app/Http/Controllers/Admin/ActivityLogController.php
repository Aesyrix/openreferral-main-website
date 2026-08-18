<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Audit trail of privileged actions (BACKEND_GUIDE §10). */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-settings');

        $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'actor_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', 'string', 'max:40'],
        ]);

        return view('admin.audit.index', [
            'logs' => ActivityLog::with('actor')
                ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->input('action').'%'))
                ->when($request->filled('actor_id'), fn ($q) => $q->where('actor_id', $request->integer('actor_id')))
                ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', $request->input('subject_type')))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
            'actions' => ActivityLog::distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
