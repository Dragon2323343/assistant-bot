<?php

namespace App\Telegram\Commands;

use App\Http\Controllers\CategoryController;
use App\Models\User;
use Telegram\Bot\Commands\Command;

class ListNotesCommand extends Command
{
    protected $name = 'list_notes';
    protected $description = 'Показать список заметок';

    public function handle()
    {
        $telegramUser = $this->getUpdate()->getMessage()->getFrom();
        $chatId = $this->getUpdate()->getMessage()->getChat()->getId();

        $user = User::getUserByTelegram($telegramUser);

        $categoryController = app(CategoryController::class);
        $categoryController->showCategories($user, $chatId, 1, null, 'show_notes_by_category');
    }
}
