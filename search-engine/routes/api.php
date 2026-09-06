<?php

use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:search-api');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:suggest-api');
Route::get('/domains', [SearchController::class, 'domains']);
