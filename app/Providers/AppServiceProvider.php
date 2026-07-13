<?php

namespace App\Providers;

use App\Services\Messenger\LoggingMessengerSender;
use App\Services\Messenger\MessengerSenderInterface;
use App\Services\Messenger\TelegramMessengerSender;
use App\Services\Sms\LoggingSmsSender;
use App\Services\Sms\SmsSenderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // SMS: заглушка-логгер до подключения реального провайдера (SMS.ru/SMSC и т.п.).
        $this->app->bind(SmsSenderInterface::class, LoggingSmsSender::class);

        // Мессенджер: Telegram подключён по-настоящему, если задан TELEGRAM_BOT_TOKEN —
        // иначе остаёмся на заглушке-логгере (см. LoggingMessengerSender).
        $this->app->bind(MessengerSenderInterface::class, function () {
            $botToken = config('services.telegram.bot_token');

            return $botToken
                ? new TelegramMessengerSender($botToken)
                : new LoggingMessengerSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
