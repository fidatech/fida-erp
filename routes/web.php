<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

require __DIR__.'/erp.php';

require __DIR__.'/erp_platform.php';
