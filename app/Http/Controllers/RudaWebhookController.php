<?php

namespace App\Http\Controllers;

use App\Models\RudaWebhookCall;
use App\Services\Ruda\RudaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Принимает вебхуки платформы Руда. В настройках партнера в Руде указывается
 * базовый адрес {APP_URL}/ruda/webhook/{RUDA_WEBHOOK_SECRET} — Руда сама дописывает
 * /eventCreated, /teamRegistered, /statisticsSubmitted. Подписи у Руды нет,
 * поэтому подлинность проверяем по секрету в пути.
 */
class RudaWebhookController
{
    public function __invoke(Request $request, RudaWebhookService $service, string $secret, string $event): Response
    {
        $expectedSecret = (string) config('services.ruda.webhook_secret');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $secret)) {
            abort(404);
        }

        $rules = match ($event) {
            'eventCreated' => [
                'id' => 'required|string',
                'name' => 'required|string',
                'eventDays' => 'present|array',
                'eventDays.*.id' => 'required|string',
            ],
            'teamRegistered' => [
                'playApplicationId' => 'required|integer',
                'eventId' => 'required|string',
                'eventName' => 'required|string',
            ],
            'statisticsSubmitted' => [
                'eventId' => 'required|string',
                'eventName' => 'required|string',
                'teams' => 'present|array',
                'teams.*.name' => 'nullable|string',
            ],
            default => abort(404),
        };

        $call = RudaWebhookCall::create([
            'event' => $event,
            'payload' => $request->all(),
            'status' => 'received',
        ]);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $call->update(['status' => 'failed', 'error' => $validator->errors()->toJson(JSON_UNESCAPED_UNICODE)]);

            return response($validator->errors()->toJson(), 422, ['Content-Type' => 'application/json']);
        }

        $data = $request->all();

        try {
            $result = match ($event) {
                'eventCreated' => $service->handleEventCreated($data),
                'teamRegistered' => $service->handleTeamRegistered($data),
                default => $service->handleStatisticsSubmitted($data),
            };
        } catch (\Throwable $e) {
            $call->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::error('[Ruda webhook] Ошибка обработки', ['event' => $event, 'call_id' => $call->id, 'error' => $e->getMessage()]);

            // 500 — пусть Руда считает доставку неуспешной (и повторит, если умеет)
            return response('', 500);
        }

        $call->update(['status' => 'processed', 'result' => $result]);

        if (! empty($result['unmatched'])) {
            Log::warning('[Ruda webhook] Не удалось сопоставить команды из статистики', [
                'call_id' => $call->id,
                'teams' => $result['unmatched'],
            ]);
        }

        return response()->noContent();
    }
}
