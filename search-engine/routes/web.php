<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('SearchPage');
});

Route::get('/search', function () {
    return Inertia::render('ResultsPage');
})->name('search');
