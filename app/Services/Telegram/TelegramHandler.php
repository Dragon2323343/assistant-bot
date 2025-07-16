<?php

namespace App\Services\Telegram;

use App\Http\Controllers\CategoryController;
use App\Models\Category;
use App\Models\User;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramHandler
{
    public function handleCommonNavigation(User $user, int $chatId, string $callbackData, $callbackQuery)
    {
        $messageId = $callbackQuery->getMessage()->getMessageId();

        if (preg_match('/^back_to_(.+)$/', $callbackData, $matches)) {
            $entity = $matches[1];

            switch ($entity) {
                case 'categories':
                    $categoryController = app(CategoryController::class);
                    $categoryController->showCategories($user, $chatId, 1, $messageId);
                    break;
            }

        } elseif (preg_match('/^page_(.+):(\d+)$/', $callbackData, $matches)) {
            $entity = $matches[1];
            $page = (int) $matches[2];

            switch ($entity) {
                case 'categories':
                    $categoryController = app(CategoryController::class);
                    $categoryController->showCategories($user, $chatId, $page, $messageId);
                    break;
            }
        }
    }
}
