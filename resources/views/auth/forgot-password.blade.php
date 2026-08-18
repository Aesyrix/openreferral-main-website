<x-layouts.guest>
    <h2 style="font-size: 20px; font-weight: 700; color: #0B1B2B; margin: 0 0 6px; text-align: center;">Reset your password</h2>
    <p style="color: #64748B; font-size: 13px; margin-bottom: 24px; text-align: center;">
        Enter your email and we'll send you a 6-digit code.
    </p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;">Email address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
            @error('email')
                <div style="color: #DC2626; font-size: 12px; margin-top: 4px;">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="or-btn or-btn-primary" style="width: 100%; padding: 12px; font-size: 15px;">
            Send reset code
        </button>

        <div style="text-align: center; margin-top: 16px;">
            <a href="{{ route('login') }}" style="color: #64748B; font-size: 13px; text-decoration: none;">← Back to sign in</a>
        </div>
    </form>
</x-layouts.guest>
