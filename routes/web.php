<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/about', function () {
    return view('about');
});
// routes/web.php
Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});

// routes/web.php
Route::get('/terms-and-conditions', function () {
    return view('terms-and-conditions');
});
Route::get('/contact', function () {
    return view('contact');
});

require __DIR__ . '/auth.php';
