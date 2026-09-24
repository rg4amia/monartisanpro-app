<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$response = $kernel->handle(
    $request = Request::create(
        '/api/v1/auth/send-otp',
        'POST',
        ['phone' => '+2250141498409', 'role' => 'client']
    )
);
echo $response->getContent();
