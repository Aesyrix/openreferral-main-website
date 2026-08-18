<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:agent,staff,admin',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => Role::from($request->role),
            'status' => UserStatus::ACTIVE,
            'created_by' => auth()->id(),
        ]);

        UserSetting::create(['user_id' => $user->id]);

        ActivityLog::create([
            'actor_id' => auth()->id(),
            'action' => 'user.created',
            'subject_type' => 'user',
            'subject_id' => $user->id,
        ]);

        return redirect()->route('admin.users.index')->with('success', "User {$user->name} created successfully.");
    }
}
