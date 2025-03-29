<?php

use Illuminate\Support\Facades\Route;
use Revolution\Bluesky\Facades\Bluesky;
use App\Http\Controllers\PostController;


Route::get('search', [PostController::class, 'search']);