<?php

use App\Livewire\MyPlate;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';
Route::middleware('auth')->group(function () {
    Route::get('/', MyPlate::class)->name('home');
});
