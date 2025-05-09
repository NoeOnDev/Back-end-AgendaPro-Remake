<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ProductController;

Route::prefix('v1')->group(function () {
    Route::apiResource('products', ProductController::class);
});
