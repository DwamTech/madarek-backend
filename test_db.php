<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Checking 'jobs' table...\n";
    if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
        echo "'jobs' table exists.\n";
    } else {
        echo "'jobs' table DOES NOT exist.\n";
    }

    // Check BackupHistory from previous run
    $latest = \App\Models\BackupHistory::latest()->first();
    echo 'Latest BackupHistory: '.($latest ? $latest->type.' ('.$latest->created_at.')' : 'None')."\n";

    echo "Testing BackupController::create with User...\n";
    $controller = new \App\Http\Controllers\BackupController;
    $request = \Illuminate\Http\Request::create('/api/backups/create', 'POST', ['mode' => 'full']);

    // Login as first admin user
    $user = \App\Models\User::where('role', 'admin')->first();
    if ($user) {
        echo 'Found Admin User: '.$user->id."\n";
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
    } else {
        echo "No Admin User found. Creating temporary one...\n";
        // Create temp user if needed, or just skip
    }

    $response = $controller->create($request);

    echo "Method called.\n";
    if ($response instanceof \Illuminate\Http\JsonResponse) {
        echo 'Response Status: '.$response->getStatusCode()."\n";
        echo 'Content: '.$response->getContent()."\n";
    } else {
        echo 'Response is NOT JSON. Type: '.get_class($response)."\n";
    }

} catch (\Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo "Trace: \n".$e->getTraceAsString()."\n";
}
