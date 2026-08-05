<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\CommerceController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('commerces', CommerceController::class)->names('commerce');
});
