<?php

namespace M2code\FileManager\Http\Controllers;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Domain\Contracts\ContentEncryptor;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function serve(Request $request, string $disk, string $path): Application|HttpResponse|ResponseFactory
    {
        if ($request->has('signature') || $request->has('expires')) {
            abort_unless($request->hasValidSignature(), Response::HTTP_FORBIDDEN);
        }

        // Decode path via the URL generator (supports encrypted + legacy base64)
        $decodedPath = app(FileUrlGenerator::class)->decodePath($path);
        if ($decodedPath === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($decodedPath)) {
            abort(404);
        }

        $mimeType = $storage->mimeType($decodedPath);
        $file = $storage->get($decodedPath);

        // Attempt decryption if encryption is enabled
        $encryptor = app(ContentEncryptor::class);
        $file = $encryptor->decrypt($file);

        return response($file, Response::HTTP_OK)
            ->header('Content-Type', $mimeType);
    }
}
