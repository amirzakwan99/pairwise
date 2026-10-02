<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['data' => ['name' => 'Pairwise API', 'status' => 'ok']]);
