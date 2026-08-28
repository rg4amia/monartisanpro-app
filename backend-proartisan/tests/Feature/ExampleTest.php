<?php

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $this->assertTrue(
        $response->isRedirect() || $response->isOk(),
        "Response status code {$response->status()} is neither 200 nor a redirect."
    );
});