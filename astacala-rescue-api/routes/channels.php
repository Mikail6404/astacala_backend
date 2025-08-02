<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin dashboard channel - only accessible by admin users
Broadcast::channel('admin-dashboard', function ($user) {
    // For now, allow all authenticated users - we'll refine this later
    return $user !== null;
});

// User-specific private channels for report notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
