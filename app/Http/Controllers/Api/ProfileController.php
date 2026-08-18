<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Http\Resources\SettingsResource;
use App\Models\AgentProfile;
use App\Models\AgentServiceArea;
use App\Models\User;
use App\Services\LeadWorkflow;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** `/me` — the agent's own profile and preferences (BACKEND_GUIDE §6). */
class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new AgentResource($this->loaded($request->user()))]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'service_areas' => ['sometimes', 'array', 'max:50'],
            'service_areas.*' => ['string', 'max:160'],
            'license_number' => ['sometimes', 'nullable', 'string', 'max:80'],
            'license_state' => ['sometimes', 'nullable', 'string', 'max:80'],
            'brokerage' => ['sometimes', 'nullable', 'string', 'max:160'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $data) {
            $user->update(array_intersect_key($data, array_flip(['name', 'phone'])));

            $profile = AgentProfile::firstOrCreate(['user_id' => $user->id]);
            $profile->fill(array_intersect_key(
                $data,
                array_flip(['license_number', 'license_state', 'brokerage', 'bio'])
            ))->save();

            if (array_key_exists('service_areas', $data)) {
                $this->syncServiceAreas($user, $data['service_areas']);
            }
        });

        return response()->json([
            'message' => 'Profile updated.',
            'data' => new AgentResource($this->loaded($user->refresh())),
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $previous = $user->rawAvatar();

        $path = $request->file('avatar')->store("avatars/{$user->id}", 'public');

        $user->update(['avatar_url' => $path]);

        if ($previous && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        return response()->json([
            'message' => 'Avatar updated.',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['That is not your current password.'],
            ]);
        }

        $user->update(['password' => $data['new_password']]);

        // Keep this session alive, drop every other one.
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->whereKeyNot($currentTokenId)->delete();

        ActivityLogger::user('user.password_changed', $user->id, [], $user);

        return response()->json(['message' => 'Password changed.']);
    }

    public function settings(Request $request): JsonResponse
    {
        return response()->json(['data' => new SettingsResource($request->user()->settingsOrDefault())]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'push_notifications' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'language' => ['sometimes', 'string', 'max:10'],
            'theme' => ['sometimes', Rule::in(['system', 'light', 'dark'])],
        ]);

        $settings = $request->user()->settingsOrDefault();
        $settings->fill($data)->save();

        return response()->json([
            'message' => 'Settings saved.',
            'data' => new SettingsResource($settings->refresh()),
        ]);
    }

    /**
     * Soft-delete the account, revoke all access, and hand any leads the agent
     * still had open back to the unassigned pool.
     */
    public function destroy(Request $request, LeadWorkflow $workflow): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $workflow) {
            $open = $user->assignedLeads()
                ->whereIn('status', [LeadStatus::NEW, LeadStatus::IN_PROGRESS])
                ->get();

            foreach ($open as $lead) {
                $workflow->unassign($lead, $user);
            }

            $user->tokens()->delete();
            $user->devices()->delete();
            $user->delete();
        });

        ActivityLogger::user('agent.self_deleted', $user->id, [], $user);

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    private function syncServiceAreas(User $user, array $areas): void
    {
        $areas = collect($areas)
            ->map(fn ($area) => trim((string) $area))
            ->filter()
            ->unique()
            ->values();

        AgentServiceArea::where('user_id', $user->id)->delete();

        foreach ($areas as $area) {
            AgentServiceArea::create(['user_id' => $user->id, 'area' => $area]);
        }
    }

    private function loaded(User $user): User
    {
        return $user->load(['agentProfile', 'serviceAreas', 'settings']);
    }
}
