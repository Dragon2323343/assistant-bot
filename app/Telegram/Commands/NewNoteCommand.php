<?php

namespace App\Telegram\Commands;

use App\Http\Controllers\CategoryController;
use App\Models\User;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class NewNoteCommand extends Command
{
    protected $name = 'new_note';
    protected $description = 'Создать новую заметку';

    public function handle()
    {
        $telegramUser = $this->update->getMessage()->getFrom();
        $chatId = $this->update->getMessage()->getChat()->getId();

        $user = User::getUserByTelegram($telegramUser);

        if ($user->categories()->count() === 0) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'У вас ещё нет категорий. Сначала создайте хотя бы одну категорию',
            ]);
            return;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Давайте создадим новую заметку!',
        ]);

        $categoryController = app(CategoryController::class);

        $categoryController->showCategories($user, $chatId, 1, null, 'select_category_for_note');

        $user->current_action = 'creating_note';
        $user->current_action_step = 'waiting_title';
        $user->save();
    }
}
