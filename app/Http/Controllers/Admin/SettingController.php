<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\Setting;
use App\Services\FcmService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Operational settings + delivery health — admin only. */
class SettingController extends Controller
{
    public function index(FcmService $fcm): View
    {
        $this->authorize('manage-settings');

        return view('admin.settings.index', [
            'settings' => Setting::all_with_defaults(),
            'pushConfigured' => $fcm->isConfigured(),
            'mailer' => config('mail.default'),
            'queue' => config('queue.default'),
            'emailStats' => EmailLog::query()
                ->groupBy('status')
                ->selectRaw('status, COUNT(*) AS aggregate')
                ->pluck('aggregate', 'status'),
            'recentEmails' => EmailLog::orderByDesc('id')->take(25)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'support_email' => ['required', 'email:rfc', 'max:190'],
            'default_lead_fee_dollars' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'otp_ttl_minutes' => ['required', 'integer', 'min:2', 'max:60'],
            'otp_max_attempts' => ['required', 'integer', 'min:3', 'max:10'],
            'otp_resend_seconds' => ['required', 'integer', 'min:15', 'max:600'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        ActivityLogger::log('settings.updated', null, 'settings', null, array_keys($data));

        return back()->with('success', 'Settings saved.');
    }
}
