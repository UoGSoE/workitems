<?php

use App\Livewire\LookingForAHand;
use App\Livewire\MyPlate;
use App\Livewire\MyTeam;
use App\Livewire\Overview;
use App\Livewire\People;
use App\Livewire\Settings;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';
Route::middleware('auth')->group(function () {
    Route::get('/', MyPlate::class)->name('home');
    Route::get('/classifieds', LookingForAHand::class)->name('looking-for-a-hand');
    Route::get('/my-team', MyTeam::class)->name('my-team');
    Route::get('/overview', Overview::class)->name('overview');
    Route::get('/people', People::class)->name('people');
    Route::get('/settings', Settings::class)->name('settings');
    Route::impersonate();
});
