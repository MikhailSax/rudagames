<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateStaffUser extends Command
{
    protected $signature = 'staff:create';

    protected $description = 'Создаёт аккаунт сотрудника (публичная регистрация отключена)';

    public function handle(): int
    {
        $name = $this->ask('Имя');
        $email = $this->ask('Email');
        $password = $this->secret('Пароль');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // email_verified_at не входит в #[Fillable] у User (осознанно, для защиты от
        // массового присвоения через формы) — проставляем отдельно.
        // Почта пока не подключена (Mail::log) — сразу считаем email подтверждённым,
        // чтобы не блокировать сотрудника письмом, которое некуда будет доставить.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("Готово: {$user->email} может входить в систему.");
        $this->comment('Рекомендуем сразу включить двухфакторную аутентификацию в настройках профиля.');

        return self::SUCCESS;
    }
}
