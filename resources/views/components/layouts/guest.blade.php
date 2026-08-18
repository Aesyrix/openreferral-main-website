<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OpenReferral Portal — Authenticated Lead Network</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="background-color: var(--or-navy); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0;">
    <div style="width: 100%; max-width: 440px; padding: 20px;">
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="{{ asset('images/logo-full.png') }}" alt="OpenReferral ONE STOP SHOP" style="max-height: 64px; width: auto; margin-bottom: 8px;">
            <p style="color: #94A3B8; font-size: 11px; letter-spacing: 1px; text-transform: uppercase; margin: 0;">ONE STOP SHOP · Exclusive Lead Portal</p>
        </div>

        <div style="background-color: white; border-radius: 16px; padding: 32px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            {{ $slot }}
        </div>

        <div style="text-align: center; margin-top: 24px; color: #64748B; font-size: 12px;">
            Licensed US Digital Real-Estate Referral Network<br>
            Florida License L24000235109 · Open Referral Inc.
        </div>
    </div>
</body>
</html>
