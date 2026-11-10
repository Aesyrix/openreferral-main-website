<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Mail\StaffInviteMail;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\UserEraser;
use App\Support\ActivityLogger;
use App\Support\Mailer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Staff management — admin only (BACKEND_GUIDE §7). */
class StaffController extends Controller
{
    public function __construct(private Mailer $mailer) {}

    public function index(): View
    {
        $this->authorize('manageStaff', User::class);

        return view('admin.staff.index', [
            'staff' => User::query()->staffAndAdmins()
                ->with('creator')
                ->withCount('createdLeads')
                ->orderByRaw("FIELD(role, 'admin', 'staff')")
                ->orderBy('name')
                ->paginate(20),
            'adminCount' => User::where('role', Role::ADMIN)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manageStaff', User::class);

        return view('admin.staff.create');
    }

    /** Creates the account and emails an invite with a temporary password. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $temporaryPassword = $data['password'] ?? Str::password(12, symbols: false);

        $user = DB::transaction(function () use ($data, $temporaryPassword) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $temporaryPassword,
                'role' => Role::from($data['role']),
                'status' => UserStatus::ACTIVE,
                'created_by' => auth()->id(),
                'email_verified_at' => now(),
            ]);

            UserSetting::create(['user_id' => $user->id]);

            return $user;
        });

        $this->mailer->send($user->email, new StaffInviteMail($user, $temporaryPassword), 'staff_invite', $user);

        ActivityLogger::user('staff.created', $user->id, ['role' => $user->role->value]);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', "{$user->name} added as {$user->role->label()} — an invite is on its way.");
    }

    public function edit(int $id): View
    {
        $this->authorize('manageStaff', User::class);

        return view('admin.staff.edit', ['member' => $this->findStaff($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $member = $this->findStaff($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($member->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        // Don't let the last admin demote themselves out of the role.
        if ($member->isAdmin()
            && $data['role'] !== 'admin'
            && User::where('role', Role::ADMIN)->count() <= 1) {
            return back()->with('error', 'You cannot demote the only remaining admin.');
        }

        $member->update([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => Role::from($data['role']),
        ] + (filled($data['password'] ?? null) ? ['password' => $data['password']] : []));

        if (filled($data['password'] ?? null)) {
            $member->tokens()->delete();
        }

        ActivityLogger::user('staff.updated', $member->id, ['role' => $member->role->value]);

        return redirect()->route('admin.staff.index')->with('success', "{$member->name} updated.");
    }

    public function suspend(int $id): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $member = $this->findStaff($id);

        if ($member->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        if ($member->isAdmin() && User::where('role', Role::ADMIN)->where('status', UserStatus::ACTIVE)->count() <= 1) {
            return back()->with('error', 'You cannot suspend the only active admin.');
        }

        $member->update(['status' => UserStatus::SUSPENDED]);
        $member->tokens()->delete();

        ActivityLogger::user('staff.suspended', $member->id);

        return back()->with('success', "{$member->name} suspended — dashboard access revoked.");
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('manageStaff', User::class);

        $member = $this->findStaff($id);
        $member->update(['status' => UserStatus::ACTIVE]);

        ActivityLogger::user('staff.restored', $member->id);

        return back()->with('success', "{$member->name} reactivated.");
    }

    /** Permanent deletion — the row leaves the users table for good. */
    public function destroy(int $id, UserEraser $eraser): RedirectResponse
    {
        $member = $this->findStaff($id);

        $this->authorize('delete', $member);

        $name = $member->name;

        $eraser->erase($member, auth()->user());

        return redirect()
            ->route('admin.staff.index')
            ->with('success', "{$name} deleted permanently. Leads they created are unchanged.");
    }

    private function findStaff(int $id): User
    {
        return User::query()->staffAndAdmins()->findOrFail($id);
    }
}
