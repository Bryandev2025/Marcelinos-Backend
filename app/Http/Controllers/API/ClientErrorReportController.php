<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\SlackErrorAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientErrorReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'source' => ['nullable', 'string', 'max:120'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'component_stack' => ['nullable', 'string', 'max:20000'],
        ]);

        SlackErrorAlerts::notifyClientPayload([
            'message' => $data['message'],
            'stack' => $data['stack'] ?? null,
            'source' => $data['source'] ?? 'client',
            'page_url' => $data['page_url'] ?? null,
            'component_stack' => $data['component_stack'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
