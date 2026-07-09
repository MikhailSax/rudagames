<?php

namespace App\Console\Commands;

use App\Models\Communication;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RunMarketingEngine extends Command
{
    protected $signature = 'marketing:run-engine';

    protected $description = 'Модуль 6: ежедневно определяет, с какой командой нужно связаться и с какой целью, '
    . 'и создаёт ЧЕРНОВИК предложения в админке. Реальную отправку (SMS/email, канал и текст) '
    . 'выбирает и подтверждает руководитель вручную.';

    // Не создавать повторный черновик той же цели, пока предыдущий не обработан (отправлен/отклонён),
    // и не повторять ту же цель чаще, чем раз в N дней после отправки.
    private const SAME_GOAL_COOLDOWN_DAYS = 7;

    // За сколько дней до игры предлагать напоминание
    private const REMINDER_DAYS_BEFORE = 2;

    public function handle(): int
    {
        $now = Carbon::now();
        $stats = ['проверено' => 0, 'черновиков_создано' => 0, 'пропущено' => 0];

        Team::query()->chunkById(200, function ($teams) use ($now, &$stats) {
            foreach ($teams as $team) {
                $stats['проверено']++;

                $goal = $this->decideGoal($team, $now);

                if ($goal === null) {
                    continue;
                }

                if ($this->hasPendingOrRecentDraft($team, $goal, $now)) {
                    $stats['пропущено']++;
                    continue;
                }

                $this->createDraft($team, $goal);
                $stats['черновиков_создано']++;
            }
        });

        $this->info('Marketing Engine завершил проход. Черновики ждут в админ-панели.');
        $this->table(array_keys($stats), [array_values($stats)]);

        return self::SUCCESS;
    }

    /**
     * Отвечает на вопросы 1-3 из ТЗ: статус -> нужен ли контакт -> какая цель.
     */
    private function decideGoal(Team $team, Carbon $now): ?string
    {
        if ($this->hasUpcomingGameSoon($team, $now)) {
            return 'напомнить';
        }

        if ($this->justReachedMilestone($team)) {
            return 'поздравить';
        }

        if (in_array($team->activity_status, ['спящая', 'потерянная'], true)) {
            return 'вернуть';
        }

        if ($team->lifecycle_stage === 'новая' && $team->activity_status === 'остывающая') {
            return 'довести до второй игры';
        }

        if ($team->activity_status === 'активная' && $this->playsOnlyOneProduct($team)) {
            return 'пригласить на новый формат';
        }

        return null;
    }

    private function hasUpcomingGameSoon(Team $team, Carbon $now): bool
    {
        return DB::table('game_participations')
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('game_participations.team_id', $team->id)
            ->whereBetween('games.played_at', [$now, $now->copy()->addDays(self::REMINDER_DAYS_BEFORE)])
            ->exists();
    }

    private function justReachedMilestone(Team $team): bool
    {
        if (!in_array($team->lifecycle_stage, ['постоянная', 'ядро сообщества'], true)) {
            return false;
        }

        return !Communication::where('team_id', $team->id)
            ->where('goal', 'поздравить')
            ->where('message_text', 'like', '%' . $team->lifecycle_stage . '%')
            ->whereIn('status', [Communication::STATUS_SENT, 'историческая пометка'])
            ->exists();
    }

    private function playsOnlyOneProduct(Team $team): bool
    {
        $distinctProducts = DB::table('game_participations')
            ->join('games', 'games.id', '=', 'game_participations.game_id')
            ->where('game_participations.team_id', $team->id)
            ->distinct('games.product_id')
            ->count('games.product_id');

        return $distinctProducts === 1;
    }

    /**
     * Не создаём новый черновик, если:
     * - уже есть необработанный черновик с той же целью (ждёт решения руководителя), или
     * - та же цель уже была реально отправлена недавно (в пределах кулдауна).
     */
    private function hasPendingOrRecentDraft(Team $team, string $goal, Carbon $now): bool
    {
        $hasPendingDraft = Communication::where('team_id', $team->id)
            ->where('goal', $goal)
            ->where('status', Communication::STATUS_DRAFT)
            ->exists();

        if ($hasPendingDraft) {
            return true;
        }

        return Communication::where('team_id', $team->id)
            ->where('goal', $goal)
            ->where('status', Communication::STATUS_SENT)
            ->where('sent_at', '>=', $now->copy()->subDays(self::SAME_GOAL_COOLDOWN_DAYS))
            ->exists();
    }

    private function createDraft(Team $team, string $goal): void
    {
        Communication::create([
            'team_id' => $team->id,
            'channel' => null, // канал выбирает руководитель при отправке
            'goal' => $goal,
            'status' => Communication::STATUS_DRAFT,
            'suggested_message_text' => $this->buildSuggestedMessage($team, $goal),
            'message_text' => null, // финальный текст — после редактирования в админке
            'sent_at' => null,
        ]);
    }

    private function buildSuggestedMessage(Team $team, string $goal): string
    {
        $name = $team->current_name;

        return match ($goal) {
            'напомнить' => "Привет, {$name}! Напоминаем — скоро ваша игра. Ждём вас!",
            'поздравить' => "Поздравляем, {$name}! Вы теперь {$team->lifecycle_stage} команда клуба. Спасибо, что с нами!",
            'вернуть' => "Привет, {$name}! Давно не виделись. Возвращайтесь на игру — соскучились по вам!",
            'довести до второй игры' => "Привет, {$name}! Понравилась первая игра? Приходите ещё — будет интересно!",
            'пригласить на новый формат' => "Привет, {$name}! Попробуйте новый формат игры — уверены, вам понравится!",
            default => "Привет, {$name}!",
        };
    }
}
