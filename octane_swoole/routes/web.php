<?php

use App\Http\Controllers\BenchmarkController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BenchmarkController::class, 'home']);
Route::get('/opcache-status', [BenchmarkController::class, 'opcacheStatus']);
