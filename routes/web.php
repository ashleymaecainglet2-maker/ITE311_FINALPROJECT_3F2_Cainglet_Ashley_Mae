<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/payments/success', 'payments.result', ['status' => 'success'])
    ->name('payments.success');

Route::view('/payments/cancelled', 'payments.result', ['status' => 'cancelled'])
    ->name('payments.cancelled');
