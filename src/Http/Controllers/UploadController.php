<?php

namespace M2code\FileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use M2code\FileManager\Application\UploadCancelledException;
use M2code\FileManager\Application\UploadService;
use M2code\FileManager\Http\Requests\UploadFormRequest;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function upload(UploadFormRequest $request, UploadService $service): JsonResponse
    {
        try {
            $options = $request->input('options', []);
            $cancelToken = $request->input('cancel_token') ?? $request->header('X-Cancel-Token');
            $file = $request->file('file') ?? $request->input('file');
            $response = $service->upload($file, $options, $cancelToken);

            return response()->json($response, Response::HTTP_CREATED);
        } catch (UploadCancelledException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 499); // Non-standard: Client Closed Request
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function cancel(Request $request, UploadService $service): JsonResponse
    {
        $token = $request->input('cancel_token');

        if (!$token) {
            return response()->json([
                'message' => 'cancel_token is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $service->cancel($token);

        return response()->json([
            'message' => 'Upload cancelled.',
            'cancel_token' => $token,
        ]);
    }
}
