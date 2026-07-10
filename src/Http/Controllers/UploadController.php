<?php

namespace M2code\FileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use M2code\FileManager\Application\UploadService;
use M2code\FileManager\DTO\UploadResponse;
use M2code\FileManager\Http\Requests\UploadFormRequest;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function upload(UploadFormRequest $request, UploadService $service): JsonResponse
    {
        try {
            $options = $request->input('options', []);
            $response = $service->upload($request->file('file'), $options);

            return response()->json($response, Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
