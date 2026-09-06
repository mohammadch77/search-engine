<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search']);
Route::get('/search/suggest', [SearchController::class, 'suggest']);
Route::get('/domains', [SearchController::class, 'domains']);
