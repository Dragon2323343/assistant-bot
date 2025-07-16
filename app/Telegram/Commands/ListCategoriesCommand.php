<?php

namespace App\Telegram\Commands;

use App\Http\Controllers\CategoryController;
use App\Models\User;
use Telegram\Bot\Commands\Command;

class ListCategoriesCommand extends Command
{
    protected $name = 'list_categories';
    protected $description = 'Показать список категорий';

    public function handle()
    {
        $telegramUser = $this->getUpdate()->getMessage()->getFrom();
        $chatId = $this->getUpdate()->getMessage()->getChat()->getId();

        $user = User::getUserByTelegram($telegramUser);

        $categoryController = app(CategoryController::class);
        $categoryController->showCategories($user, $chatId);
    }
}
