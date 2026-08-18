<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('leads:expire-accept-windows')->everyFiveMinutes();
Schedule::command('subscriptions:check-expirations')->daily();
