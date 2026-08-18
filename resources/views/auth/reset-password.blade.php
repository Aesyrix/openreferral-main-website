<x-layouts.guest>
    <h2 style="font-size: 20px; font-weight: 700; color: #0B1B2B; margin: 0 0 6px; text-align: center;">Enter your code</h2>
    <p style="color: #64748B; font-size: 13px; margin-bottom: 22px; text-align: center;">
        Paste the 6-digit code from your email and choose a new password.
    </p>

    @if(session('success'))
        <div style="background-color: #E8F3FC; color: #0E5FA8; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email address</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            @error('email')<div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">6-digit code</label>
            <input type="text" name="code" inputmode="numeric" maxlength="6" required autofocus
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 20px; letter-spacing: 8px; text-align: center; box-sizing: border-box;">
            @error('code')<div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">New password</label>
            <input type="password" name="password" required autocomplete="new-password"
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            @error('password')<div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
        </div>

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Confirm new password</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
        </div>

        <button type="submit" class="or-btn or-btn-primary" style="width: 100%; padding: 12px; font-size: 15px;">
            Set new password
        </button>

        <div style="text-align: center; margin-top: 16px;">
            <a href="{{ route('password.request') }}" style="color: #64748B; font-size: 13px; text-decoration: none;">Need a new code?</a>
        </div>
    </form>
</x-layouts.guest>
