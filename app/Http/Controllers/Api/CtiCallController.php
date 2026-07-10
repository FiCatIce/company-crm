<?php

namespace App\Http\Controllers\Api;

use App\Actions\Cti\IngestCall;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCtiCallRequest;
use Illuminate\Http\JsonResponse;

class CtiCallController extends Controller
{
    /**
     * Ingest a single call event from the PBX/connector. Thin wrapper — the
     * orchestration lives in the IngestCall action.
     */
    public function __invoke(StoreCtiCallRequest $request, IngestCall $ingest): JsonResponse
    {
        $result = $ingest($request->validated());

        return response()->json($result['payload'], $result['code']);
    }
}
