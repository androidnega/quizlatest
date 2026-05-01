<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\UniversityController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    if (auth()->user()?->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', 'admin'])
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/universities', [UniversityController::class, 'index'])->name('universities.index');
        Route::get('/universities/create', [UniversityController::class, 'create'])->name('universities.create');
        Route::post('/universities', [UniversityController::class, 'store'])->name('universities.store');
        Route::get('/universities/{university}/edit', [UniversityController::class, 'edit'])->name('universities.edit');
        Route::put('/universities/{university}', [UniversityController::class, 'update'])->name('universities.update');

        Route::get('/coordinators', [CoordinatorController::class, 'index'])->name('coordinators.index');
        Route::get('/coordinators/create', [CoordinatorController::class, 'create'])->name('coordinators.create');
        Route::post('/coordinators', [CoordinatorController::class, 'store'])->name('coordinators.store');
        Route::get('/coordinators/{coordinator}/edit', [CoordinatorController::class, 'edit'])->name('coordinators.edit');
        Route::put('/coordinators/{coordinator}', [CoordinatorController::class, 'update'])->name('coordinators.update');
    });

require __DIR__.'/auth.php';
