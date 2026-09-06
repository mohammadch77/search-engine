<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\SearchLogController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('SearchPage');
});

Route::get('/search', function () {
    return Inertia::render('ResultsPage');
})->name('search');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/domains', [DomainController::class, 'index'])->name('domains');
        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::patch('/domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::post('/domains/{domain}/recrawl', [DomainController::class, 'recrawl'])->name('domains.recrawl');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
        Route::get('/searches', [SearchLogController::class, 'index'])->name('searches');
    });
});
