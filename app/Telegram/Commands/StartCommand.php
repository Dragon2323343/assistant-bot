<?php

namespace App\Telegram\Commands;

use App\Models\User;
use Telegram\Bot\Commands\Command;

class StartCommand extends Command
{
    protected $name = 'start';

    protected $description = 'Начальное приветствие';

    public function handle()
    {
        $telegramUser = $this->update->getMessage()->getFrom();

        User::createUser($telegramUser);

        $this->replyWithMessage([
            'text' => "Привет! Я твой личный ассистент.\n" .
                "Используй команды для создания заметок и напоминаний.",
        ]);
    }
}
