<?php

use Illuminate\Support\Facades\Route;
use Ar4min\ErpAgent\Http\Controllers\LicenseController;

Route::get('/license-expired', [LicenseController::class, 'expired'])
    ->name('license.expired');
