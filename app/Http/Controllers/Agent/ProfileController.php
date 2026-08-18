<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentProfile;
use App\Models\AgentServiceArea;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** The agent's own profile, avatar, password, and notification preferences. */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $agent = $request->user()->load(['agentProfile', 'serviceAreas', 'settings']);

        return view('agent.profile', [
            'agent' => $agent,
            'profile' => $agent->agentProfile,
            'settings' => $agent->settingsOrDefault(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'license_number' => ['nullable', 'string', 'max:80'],
            'license_state' => ['nullable', 'string', 'max:80'],
            'brokerage' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'service_areas' => ['nullable', 'string', 'max:2000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $agent = $request->user();

        DB::transaction(function () use ($agent, $data, $request) {
            $agent->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ]);

            AgentProfile::updateOrCreate(['user_id' => $agent->id], [
                'license_number' => $data['license_number'] ?? null,
                'license_state' => $data['license_state'] ?? null,
                'brokerage' => $data['brokerage'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]);

            // Service areas arrive as one comma or newline separated field.
            $areas = collect(preg_split('/[\r\n,]+/', (string) ($data['service_areas'] ?? '')))
                ->map(fn ($area) => trim($area))
                ->filter()
                ->unique();

            AgentServiceArea::where('user_id', $agent->id)->delete();

            foreach ($areas as $area) {
                AgentServiceArea::create(['user_id' => $agent->id, 'area' => $area]);
            }

            if ($request->hasFile('avatar')) {
                $previous = $agent->rawAvatar();
                $path = $request->file('avatar')->store("avatars/{$agent->id}", 'public');
                $agent->update(['avatar_url' => $path]);

                if ($previous && ! str_starts_with($previous, 'http')) {
                    Storage::disk('public')->delete($previous);
                }
            }
        });

        return back()->with('success', 'Profile updated.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $agent = $request->user();

        if (! Hash::check($data['current_password'], $agent->password)) {
            return back()->with('error', 'That is not your current password.');
        }

        $agent->update(['password' => $data['new_password']]);
        $agent->tokens()->delete();

        ActivityLogger::user('user.password_changed', $agent->id, [], $agent);

        return back()->with('success', 'Password changed. Mobile sessions were signed out.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'push_notifications' => ['nullable', 'boolean'],
            'email_notifications' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:10'],
            'theme' => ['nullable', Rule::in(['system', 'light', 'dark'])],
        ]);

        $settings = $request->user()->settingsOrDefault();

        $settings->update([
            'push_notifications' => $request->boolean('push_notifications'),
            'email_notifications' => $request->boolean('email_notifications'),
            'language' => $data['language'] ?? $settings->language,
            'theme' => $data['theme'] ?? $settings->theme,
        ]);

        return back()->with('success', 'Notification preferences saved.');
    }
}
