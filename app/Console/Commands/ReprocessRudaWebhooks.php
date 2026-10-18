<?php

namespace App\Console\Commands;

use App\Models\RudaWebhookCall;
use App\Services\Ruda\RudaWebhookService;
use Illuminate\Console\Command;

class ReprocessRudaWebhooks extends Command
{
    protected $signature = 'ruda:reprocess
                            {ids?* : ID записей из журнала ruda_webhook_calls}
                            {--all : Все записи журнала по порядку}
                            {--event= : Только события этого типа, напр. statisticsSubmitted}';

    protected $description = 'Повторно обрабатывает сохранённые вебхуки Руды (после исправлений в сопоставлении)';

    public function handle(RudaWebhookService $service): int
    {
        $query = RudaWebhookCall::query()->orderBy('id');

        if ($event = $this->option('event')) {
            $query->where('event', $event);
        } elseif (! $this->option('all')) {
            $ids = $this->argument('ids');

            if (empty($ids)) {
                $this->error('Укажите ID записей, --event или --all');

                return self::FAILURE;
            }

            $query->whereIn('id', $ids);
        }

        foreach ($query->get() as $call) {
            try {
                $result = match ($call->event) {
                    'eventCreated' => $service->handleEventCreated($call->payload),
                    'teamRegistered' => $service->handleTeamRegistered($call->payload),
                    default => $service->handleStatisticsSubmitted($call->payload),
                };
                $call->update(['status' => 'processed', 'result' => $result, 'error' => null]);
                $this->info("#{$call->id} {$call->event}: ".json_encode($result, JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $call->update(['status' => 'failed', 'error' => $e->getMessage()]);
                $this->error("#{$call->id} {$call->event}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
