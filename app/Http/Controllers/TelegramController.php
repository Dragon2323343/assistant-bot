<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram\TelegramHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Services\Telegram\TelegramCommandRegistrar;

class TelegramController extends Controller
{
    public function handleCallbackQuery($callbackQuery, User $user, $chatId)
    {
        try {
            $callbackData = $callbackQuery->getData();

            foreach (config('telegram_callbacks') as $prefix => [$controllerClass, $method]) {
                if (str_starts_with($callbackData, $prefix)) {
                    $controller = app($controllerClass);

                    $controller->$method($user, $chatId, $callbackData, $callbackQuery);

                    return response('ok', 200);
                }
            }

            return response('no callback matched', 200);
        } catch (\Throwable $e) {
            Log::error('Telegram callback error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'chat_id' => $chatId ?? null,
            ]);

            return response('Internal Server Error', 500);
        }
    }
    public function handleMessage($message, $update)
    {
        try {
            $telegramUser = $message->getFrom();
            $chatId = $message->getChat()->getId();
            $text = trim($message->getText());

            $user = User::getUserByTelegram($telegramUser, $chatId);

            if (str_starts_with($text, '/')) {
                $user->current_action = null;
                $user->current_action_step = null;
                $user->temp_data = null;
                $user->save();

                $command = ltrim($text, '/');
                Telegram::triggerCommand($command, $update);
                return response('ok', 200);
            }

            if ($user->current_action === 'creating_category' && $user->current_action_step === 'waiting_name') {
                $categoryController = app(CategoryController::class);
                $categoryController->createCategory($user, $chatId, $text);

                return response('ok', 200);
            }

            if ($user->current_action === 'editing_category' && $user->current_action_step === 'waiting_new_name') {
                $categoryController = app(CategoryController::class);
                $categoryController->updateCategoryName($user, $chatId, $text);

                return response('ok', 200);
            }

            if ($user->current_action === 'creating_note') {
                $noteController = app(NoteController::class);
                $noteController->handleUserMessage($user, $chatId, $message);

                return response('ok', 200);
            }

            if ($user->current_action === 'editing_note') {
                $noteController = app(NoteController::class);
                $noteController->handleUserMessage($user, $chatId, $message);

                return response('ok', 200);
            }

            Telegram::commandsHandler(true);
            return response('ok', 200);
        } catch (\Throwable $e) {
            Log::error('Telegram message error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $message->getChat() ? $message->getChat()->getId() : null,
            ]);

            return response('Internal Server Error', 500);
        }
    }
}
