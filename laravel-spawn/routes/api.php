<?php

use App\Http\Controllers\BenchmarkController;
use Illuminate\Support\Facades\Route;

Route::get('/hello', [BenchmarkController::class, 'hello']);
Route::get('/test', [BenchmarkController::class, 'test']);
Route::get('/bench', [BenchmarkController::class, 'bench']);
Route::get('/debug/connections', [BenchmarkController::class, 'connections']);
