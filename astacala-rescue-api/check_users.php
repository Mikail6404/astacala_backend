<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->boot();

$users = \App\Models\User::all(['id', 'name', 'email'])->toArray();
echo json_encode($users, JSON_PRETTY_PRINT);
