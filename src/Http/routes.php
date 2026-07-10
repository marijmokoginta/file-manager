<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use M2code\FileManager\Http\Controllers\FileController;
use M2code\FileManager\Http\Controllers\UploadController;

Route::get('file/{disk}/{path}', [FileController::class, 'serve'])
    ->where('path', '.*')
    ->name('file-manager.serve');

$middleware = Config::get('file-manager.api.middleware', 'file-manager.api');

Route::post('upload', [UploadController::class, 'upload'])
    ->middleware($middleware)
    ->name('file-manager.upload');

Route::post('upload/cancel', [UploadController::class, 'cancel'])
    ->middleware($middleware)
    ->name('file-manager.upload.cancel');
