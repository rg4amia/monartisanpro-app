<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create(
        '/api/v1/auth/send-otp',
        'POST',
        ['phone' => '+2250141498409', 'role' => 'client']
    )
);
echo $response->getContent();
