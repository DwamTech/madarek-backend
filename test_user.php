<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->bootstrap();

try {
    echo "Testing User Model isAdmin method...\n";
    $user = new \App\Models\User;
    if (method_exists($user, 'isAdmin')) {
        echo "isAdmin method exists.\n";
    } else {
        echo "isAdmin method DOES NOT exist.\n";
    }

} catch (\Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
