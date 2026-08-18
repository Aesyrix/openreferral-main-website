<x-layouts.guest>
    <h2 style="font-size: 20px; font-weight: 700; color: #0B1B2B; margin: 0 0 6px; text-align: center;">Sign in</h2>
    <p style="color: #64748B; font-size: 13px; margin-bottom: 24px; text-align: center;">
        Staff and admin dashboard access. Agents use the mobile app.
    </p>

    @if(session('success'))
        <div style="background-color: #DCFCE7; color: #15803D; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            @error('email')
                <div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label style="font-size: 13px; font-weight: 600; color: #334155;">Password</label>
                <a href="{{ route('password.request') }}" style="color: #1B86E0; font-size: 12px; text-decoration: none;">Forgot password?</a>
            </div>
            <input type="password" name="password" required autocomplete="current-password"
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            @error('password')
                <div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; margin-bottom: 20px; cursor: pointer;">
            <input type="checkbox" name="remember" value="1"> Keep me signed in
        </label>

        <button type="submit" class="or-btn or-btn-primary" style="width: 100%; padding: 12px; font-size: 15px;">
            Sign in →
        </button>
    </form>
</x-layouts.guest>
