<?php

use Illuminate\Support\Facades\Route;

$frontend = function () {
    $index = public_path('frontend/index.html');
    abort_unless(is_file($index), 503, 'A build do frontend não está disponível.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
};

Route::get('/', $frontend);
Route::get('/{area}/{path?}', $frontend)
    ->where(['area' => 'admin|auth', 'path' => '.*']);
