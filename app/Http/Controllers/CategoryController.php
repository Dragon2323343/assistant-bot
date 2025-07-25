<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Services\Telegram\TelegramHandler;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

class CategoryController extends Controller
{
    protected $telegramHandler;

    public function __construct(TelegramHandler $telegramHandler)
    {
        $this->telegramHandler = $telegramHandler;
    }

    public function createCategory(User $user, int $chatId, string $categoryName)
    {
        try {
            Category::createCategory($user, $categoryName);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Категория {$categoryName} успешно создана!",
            ]);

            $user->current_action = null;
            $user->current_action_step = null;
            $user->save();
        } catch (\Throwable $e) {
            \Log::error('Ошибка при создании категории', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'category_name' => $categoryName,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Произошла ошибка при создании категории. Попробуйте позже.",
            ]);
        }
    }

    private function deleteCategory(User $user, int $chatId, Category $category, int $messageId)
    {
        try {
            $categoryName = $category->name;
            $category->delete();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Категория {$categoryName} успешно удалена",
            ]);

            $this->showCategories($user, $chatId, 1, $messageId);
        } catch (\Throwable $e) {
            \Log::error('Ошибка при удалении категории', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Ошибка при удалении категории. Попробуйте позже.",
            ]);
        }
    }

    public function updateCategoryName(User $user, int $chatId, string $newName)
    {
        try {
            $tempData = json_decode($user->temp_data, true);

            $categoryId = $tempData['category_id'] ?? null;

            $category = $user->categories()->find($categoryId);

            if (!$category) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Категория для редактирования не найдена',
                ]);
                return;
            }

            $oldName = $category->name;

            $category->name = trim($newName);
            $category->save();

            $user->current_action = null;
            $user->current_action_step = null;
            $user->temp_data = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Категория {$oldName} успешно переименована в {$category->name}",
            ]);

            $this->showCategories($user, $chatId);
        } catch (\Throwable $e) {
            \Log::error('Ошибка при обновлении имени категории', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'new_name' => $newName,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Произошла ошибка при переименовании категории. Попробуйте позже.",
            ]);
        }
    }

    public function handleCategoryCallback(User $user, int $chatId, string $callbackData, $callbackQuery)
    {
        try {
            $parts = explode(':', $callbackData);
            [$action, $categoryId] = $parts;

            $category = $user->categories()->find($categoryId);
            if (!$category) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Категория не найдена.',
                ]);

                return response('ok', 200);
            }

            $messageId = $callbackQuery->getMessage()->getMessageId();

            switch ($action) {
                case 'select_category':
                    $this->sendCategoryActionsKeyboard($chatId, $messageId, $category);
                    break;

                case 'edit_category':
                    $this->startEditingCategory($user, $chatId, $category);
                    break;

                case 'delete_category':
                    $this->deleteCategory($user, $chatId, $category, $messageId);
                    break;

                default:
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Неизвестное действие',
                    ]);
                    break;
            }

            return response('ok', 200);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleCategoryCallback', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'callback_data' => $callbackData,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке команды. Попробуйте позже.',
            ]);
        }
    }

    public function showCategories(User $user, int $chatId, int $page = 1, ?int $messageId = null, string $callbackPrefix = 'select_category')
    {
        try {
            $perPage = 8;
            $totalCategories = $user->categories()->count();
            $totalPages = (int) ceil($totalCategories / $perPage);

            $categories = $user->categories()
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            if ($categories->isEmpty()) {
                $text = 'У вас ещё нет категорий';
                if ($messageId) {
                    Telegram::editMessageText([
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $text,
                    ]);
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $text,
                    ]);
                }
                return;
            }

            $inlineKeyboard = [];

            foreach ($categories as $category) {
                $inlineKeyboard[] = [
                    ['text' => $category->name, 'callback_data' => "{$callbackPrefix}:{$category->id}"]
                ];
            }

            $navigationButtons = [];

            if ($page > 1) {
                $navigationButtons[] = [
                    'text' => '⬅️ Назад',
                    'callback_data' => 'page_categories:' . ($page - 1),
                ];
            }

            if ($page < $totalPages) {
                $navigationButtons[] = [
                    'text' => 'Вперёд ➡️',
                    'callback_data' => 'page_categories:' . ($page + 1),
                ];
            }

            if (!empty($navigationButtons)) {
                $inlineKeyboard[] = $navigationButtons;
            }

            $text = "Выберите категорию (страница {$page} из {$totalPages}):";

            if ($messageId) {
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
                ]);
            } else {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('Ошибка при показе категорий', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при получении списка категорий.',
            ]);
        }
    }

    private function startEditingCategory(User $user, int $chatId, Category $category)
    {
        try {
            $tempData = [
                'category_id' => $category->id,
            ];

            $user->update([
                'current_action' => 'editing_category',
                'current_action_step' => 'waiting_new_name',
                'temp_data' => json_encode($tempData),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "Введите новое название для категории {$category->name}:",
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка при переходе к редактированию категории', [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при переходе к редактированию категории.',
            ]);
        }
    }

    private function sendCategoryActionsKeyboard(int $chatId, int $messageId, Category $category)
    {
        try {
            $inlineKeyboard = [
                [
                    ['text' => '✏️ Редактировать', 'callback_data' => "edit_category:{$category->id}"],
                    ['text' => '🗑️ Удалить', 'callback_data' => "delete_category:{$category->id}"],
                ],
                [
                    ['text' => '🔙 Назад', 'callback_data' => 'back_to_categories'],
                ],
            ];

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => "Категория: {$category->name}\nВыберите действие:",
                'reply_markup' => json_encode([
                    'inline_keyboard' => $inlineKeyboard,
                ]),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка при отображении клавиатуры действий категории', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при показе меню категории.',
            ]);
        }
    }
}
