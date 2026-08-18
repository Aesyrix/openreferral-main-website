<x-emails.layout :heading="$isSignup ? 'Verify your email' : 'Reset your password'">
    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
        {{ $name ? 'Hi '.$name.',' : 'Hi,' }}
    </p>

    <p style="margin:0 0 20px 0; font-size:15px; line-height:1.6;">
        {{ $isSignup
            ? 'Use the code below to finish creating your Open Referral agent account.'
            : 'Use the code below to reset your Open Referral password.' }}
    </p>

    <div style="margin:0 0 20px 0; padding:18px; background-color:#E8F3FC; border:1px solid #BFDDF6; border-radius:10px; text-align:center;">
        <span style="font-size:32px; font-weight:700; letter-spacing:8px; color:#0E5FA8;">{{ $code }}</span>
    </div>

    <p style="margin:0 0 8px 0; font-size:14px; line-height:1.6; color:#5B6B7C;">
        This code expires in {{ $ttlMinutes }} minutes and can only be used once.
    </p>

    <p style="margin:0; font-size:14px; line-height:1.6; color:#5B6B7C;">
        If you didn't request it, you can safely ignore this email — nothing has changed on your account.
    </p>
</x-emails.layout>
