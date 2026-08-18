<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $slaHours = Setting::get('sla_hours', 4);
        $staleDays = Setting::get('stale_days', 5);
        $maxUploadMb = Setting::get('max_upload_mb', 15);
        $companyName = Setting::get('company_name', 'Open Referral / ONE STOP SHOP');

        return view('admin.settings.index', compact('slaHours', 'staleDays', 'maxUploadMb', 'companyName'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'sla_hours' => 'required|integer|min:1|max:48',
            'stale_days' => 'required|integer|min:1|max:30',
            'max_upload_mb' => 'required|integer|min:1|max:50',
            'company_name' => 'required|string',
        ]);

        Setting::set('sla_hours', (int)$request->sla_hours);
        Setting::set('stale_days', (int)$request->stale_days);
        Setting::set('max_upload_mb', (int)$request->max_upload_mb);
        Setting::set('company_name', $request->company_name);

        return back()->with('success', 'System settings updated successfully.');
    }
}
