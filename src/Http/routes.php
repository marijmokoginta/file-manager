<?php

use Illuminate\Support\Facades\Route;
use M2code\FileManager\Http\Controllers\FileController;

Route::get('file/{disk}/{path}', [FileController::class, 'serve'])
    ->where('path', '.*')
    ->name('file-manager.serve');