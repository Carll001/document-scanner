<?php

use App\Http\Controllers\AfsController;
use App\Http\Controllers\ClientUserController;
use App\Http\Controllers\ClientDataController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');


Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('clients/users')->name('clients.users.')->group(function () {
        Route::get('/', [ClientUserController::class, 'index'])->name('index');
        Route::get('/{user}', [ClientUserController::class, 'show'])->name('show'); // ✅ must exist
        Route::post('/', [ClientUserController::class, 'store'])->name('store');
        Route::put('/{user}', [ClientUserController::class, 'update'])->name('update');
        Route::delete('/{user}', [ClientUserController::class, 'destroy'])->name('destroy');
    });


    Route::prefix('afs')->name('afs.')->group(function () {
        Route::get('/', [AfsController::class, 'index'])->name('index');
        Route::get('/create', [AfsController::class, 'create'])->name('create');
        Route::get('/placeholder', [AfsController::class, 'templatePlaceholders'])
            ->name('placeholder');
        Route::post('/', [AfsController::class, 'store'])->name('store');
        Route::post('/parse', [AfsController::class, 'parse'])->name('parse');
        Route::get('/{afs}', [AfsController::class, 'show'])->name('show');
        Route::get('/{afs}/edit', [AfsController::class, 'edit'])->name('edit');
        Route::patch('/{afs}', [AfsController::class, 'update'])->name('update');
        Route::delete('/{afs}', [AfsController::class, 'destroy'])->name('destroy');
    });
});



require __DIR__ . '/settings.php';
