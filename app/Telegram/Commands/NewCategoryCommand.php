<?php

namespace App\Telegram\Commands;

use App\Models\User;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class NewCategoryCommand extends Command
{
    protected $name = 'new_category';
    protected $description = 'Создать новую категорию';

    public function handle()
    {
        $telegramUser = $this->update->getMessage()->getFrom();
        $chatId = $this->update->getMessage()->getChat()->getId();

        $user = User::getUserByTelegram($telegramUser);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Пожалуйста, введите название новой категории:',
        ]);

        $user->current_action = 'creating_category';
        $user->current_action_step = 'waiting_name';
        $user->save();
    }
}
