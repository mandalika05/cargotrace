<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// REST API CargoTrace v1 (P11)
require __DIR__.'/api_v1.php';
