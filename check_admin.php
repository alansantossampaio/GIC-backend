<?php
require 'bootstrap/app.php';

$app = new App\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check admin users
$users = \App\Models\User::all(['id', 'name', 'email', 'is_system_admin']);
echo "=== All Users ===\n";
foreach ($users as $user) {
    echo sprintf("ID: %d, Name: %s, Email: %s, is_system_admin: %s\n",
        $user->id,
        $user->name,
        $user->email,
        $user->is_system_admin ? 'true' : 'false'
    );
}

// Check admin user specifically
echo "\n=== Checking admin@admin.com.br ===\n";
$admin = \App\Models\User::where('email', 'admin@admin.com.br')->first();
if ($admin) {
    echo "Found: {$admin->name} (ID: {$admin->id})\n";
    echo "is_system_admin: " . ($admin->is_system_admin ? 'true' : 'false') . "\n";

    // Check permissions
    $permissionService = new \App\Services\PermissionService();
    $permissions = $permissionService->getAccessibleFeatures($admin);
    echo "Permissions: " . implode(', ', $permissions) . "\n";
    echo "Is Admin: " . ($permissionService->isAdmin($admin) ? 'true' : 'false') . "\n";
} else {
    echo "Admin not found\n";
}
