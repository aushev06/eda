<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

afterEach(function () {
    putenv('TRUSTED_PROXIES');
    TrustProxies::flushState();
});

it('honours X-Forwarded-Proto from proxies listed in TRUSTED_PROXIES', function () {
    putenv('TRUSTED_PROXIES=*');
    $this->refreshApplication();

    Route::get('/_proxy-check', fn (Request $request) => ['secure' => $request->isSecure()]);

    $this->get('/_proxy-check', ['X-Forwarded-Proto' => 'https'])
        ->assertJson(['secure' => true]);
});

it('ignores forwarded headers when no proxy is configured', function () {
    TrustProxies::flushState();
    $this->refreshApplication();

    Route::get('/_proxy-check', fn (Request $request) => ['secure' => $request->isSecure()]);

    $this->get('/_proxy-check', ['X-Forwarded-Proto' => 'https'])
        ->assertJson(['secure' => false]);
});
