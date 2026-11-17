<?php

declare(strict_types=1);

namespace Fiachehr\LaravelLogger\Http;

use Fiachehr\LaravelLogger\Security\DashboardLogin;
use Fiachehr\LaravelLogger\Storage\LogSource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController
{
    public function index(): View
    {
        return view('log-explorer::dashboard', ['credentialsMode' => app(DashboardLogin::class)->mode() === 'credentials']);
    }

    public function entries(Request $request, LogSource $source): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'q' => ['sometimes', 'string', 'max:120'],
            'level' => ['sometimes', Rule::in(['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'])],
            'service' => ['sometimes', 'string', 'max:120'],
            'event' => ['sometimes', 'string', 'max:120'],
            'request_id' => ['sometimes', 'string', 'regex:/\A[a-zA-Z0-9_-]{16,64}\z/D'],
            'exception' => ['sometimes', 'string', 'max:120'],
            'route' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'integer', 'between:100,599'],
            'from' => ['sometimes', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'until' => ['sometimes', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'order' => ['sometimes', Rule::in(['newest', 'oldest'])],
            'cursor' => ['sometimes', 'string', 'max:16384'],
        ])->validate();
        $cursor = $filters['cursor'] ?? null;
        unset($filters['cursor']);
        try {
            return response()->json($source->search($filters, $cursor, max(1, min(100, (int) config('log-explorer.dashboard.max_results', 50)))));
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Log storage is unavailable or exceeds its quota. Run log-explorer:doctor.'], 503);
        }
    }

    public function asset(string $asset): Response
    {
        $allowed = ['explorer-v1.css' => 'text/css', 'explorer-v1.js' => 'text/javascript'];
        abort_unless(isset($allowed[$asset]), 404);

        return response(file_get_contents(__DIR__.'/../../resources/assets/'.$asset), 200, ['Content-Type' => $allowed[$asset].'; charset=UTF-8']);
    }
}
