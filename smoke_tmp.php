<?php
// Smoke-render every dashboard + agent page as the right role.
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\User::where('role','admin')->first();
$staff = App\Models\User::where('role','staff')->first();
$agent = App\Models\User::where('role','agent')->where('status','active')->first();
$lead  = App\Models\Lead::whereNotNull('assigned_to')->first();
$agentLead = App\Models\Lead::where('assigned_to', $agent->id)->first();

$pages = [
  ['admin', '/admin'], ['admin', '/admin/leads'], ['admin', '/admin/leads/create'],
  ['admin', "/admin/leads/{$lead->id}"], ['admin', "/admin/leads/{$lead->id}/edit"],
  ['admin', "/admin/leads/{$lead->id}/feedback"],
  ['admin', '/admin/agents'], ['admin', "/admin/agents/{$agent->id}"],
  ['admin', '/admin/analytics'], ['admin', '/admin/analytics/series'],
  ['admin', '/admin/revenue'], ['admin', '/admin/revenue/series'],
  ['admin', '/admin/staff'], ['admin', '/admin/staff/create'], ['admin', "/admin/staff/{$staff->id}/edit"],
  ['admin', '/admin/content'], ['admin', '/admin/content/about/edit'],
  ['admin', '/admin/settings'], ['admin', '/admin/audit'],
  ['admin', '/notifications'], ['admin', '/search?q=Miami'],
  ['staff', '/admin'], ['staff', '/admin/leads'], ['staff', '/admin/agents'], ['staff', '/admin/analytics'],
  ['staff', '/admin/revenue'], ['staff', '/admin/staff'], ['staff', '/admin/settings'],
  ['agent', '/agent'], ['agent', '/agent/leads'], ['agent', "/agent/leads/{$agentLead->id}"], ['agent', '/agent/profile'],
  ['agent', '/notifications'], ['agent', '/admin'],
  ['guest', '/login'], ['guest', '/forgot-password'], ['guest', '/reset-password'],
];

$users = ['admin' => $admin, 'staff' => $staff, 'agent' => $agent, 'guest' => null];
$fail = 0;

foreach ($pages as [$role, $uri]) {
    if ($users[$role]) { Illuminate\Support\Facades\Auth::login($users[$role]); }
    else { Illuminate\Support\Facades\Auth::logout(); }

    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession($app['session']->driver());

    try {
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $ok = in_array($status, [200, 302, 403]);
        if (!$ok) { $fail++; }
        printf("%-6s %-40s %s%s\n", $role, $uri, $status, $ok ? '' : '   <-- FAIL');
        if (!$ok) {
            $body = strip_tags($response->getContent());
            echo '        '.trim(preg_replace('/\s+/', ' ', mb_substr($body, 0, 400)))."\n";
        }
    } catch (Throwable $e) {
        $fail++;
        printf("%-6s %-40s EX   <-- %s\n", $role, $uri, $e->getMessage());
        echo '        '.$e->getFile().':'.$e->getLine()."\n";
    }
}

echo $fail === 0 ? "\nALL PAGES OK\n" : "\n{$fail} FAILURES\n";
