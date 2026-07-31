<?php

use Illuminate\Support\Facades\Route;

Route::middleware('public.locale')->group(function (): void {
    Route::get('/', function () {
        return view('welcome');
    });
});
