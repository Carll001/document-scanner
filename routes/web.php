<?php

use App\Http\Controllers\AfsController;
use App\Http\Controllers\ClientDataController;
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
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');


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


    Route::prefix('client-data')->name('clientData.')->group(function() {
        Route::get('/', [ClientDataController::class, 'index'])->name('index');
        Route::get('/create', [ClientDataController::class, 'create'])->name('create');
        Route::post('/', [ClientDataController::class, 'store'])->name('store');
        Route::get('/{clientData}', [ClientDataController::class, 'show'])->name('show');
        Route::get('/{clientData}/edit', [ClientDataController::class, 'edit'])->name('edit');
        Route::patch('/{clientData}', [ClientDataController::class, 'update'])->name('update');
        Route::delete('/{clientData}', [ClientDataController::class, 'destroy'])->name('destroy');
        
    });
});



require __DIR__ . '/settings.php';
