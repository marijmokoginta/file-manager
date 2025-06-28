<?php

namespace M2code\FileManager\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function serve(Request $request, string $disk, string $path): Application|HttpResponse|ResponseFactory
    {
        $path = base64_decode($path);

        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            abort(404);
        }

        $mimeType = $storage->mimeType($path);
        $file = $storage->get($path);

        return response($file, Response::HTTP_OK)
            ->header('Content-Type', $mimeType);
    }
}