<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "Creating test admin user...\n";

// Create admin user
$admin = User::firstOrCreate(
    ['email' => 'admin@test.com'],
    [
        'name' => 'Test Admin',
        'password' => Hash::make('password123'),
        'role' => 'admin',
        'is_active' => true,
        'email_verified_at' => now(),
        'phone' => '+628123456789',
        'birth_date' => '1985-01-01',
        'place_of_birth' => 'Jakarta',
        'member_number' => 'ADMIN-001',
        'organization' => 'Astacala Rescue',
    ]
);

echo 'Admin created: '.$admin->email."\n";

// Create volunteer users
for ($i = 1; $i <= 3; $i++) {
    $volunteer = User::firstOrCreate(
        ['email' => "volunteer$i@test.com"],
        [
            'name' => "Test Volunteer $i",
            'password' => Hash::make('password123'),
            'role' => 'volunteer',
            'is_active' => true,
            'email_verified_at' => now(),
            'phone' => '+62812345678'.$i,
            'birth_date' => '199'.$i.'-0'.$i.'-15',
            'place_of_birth' => 'Test City '.$i,
            'member_number' => 'VOL-00'.$i,
            'organization' => 'Test Organization',
        ]
    );

    echo 'Volunteer created: '.$volunteer->email."\n";
}

echo "Test users ready!\n";
