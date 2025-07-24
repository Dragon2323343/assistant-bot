<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\User;
use App\Http\Controllers\TelegramController;
use App\Services\Telegram\TelegramCommandRegistrar;

class TelegramPolling extends Command
{
    protected $signature = 'telegram:polling';
    protected $description = 'Start polling Telegram updates';

    public function handle()
    {
        $offset = 0;

        TelegramCommandRegistrar::registerCommands();

        $telegramController = new TelegramController();

        $this->info('Start polling...');

        while (true) {
            $updates = Telegram::getUpdates([
                'offset' => $offset,
                'timeout' => 10
            ]);

            foreach ($updates as $update) {
                $offset = $update->getUpdateId() + 1;

                if ($callbackQuery = $update->getCallbackQuery()) {
                    $telegramUser = $callbackQuery->getFrom();
                    $chatId = $callbackQuery->getMessage()->getChat()->getId();

                    $user = User::getUserByTelegram($telegramUser, $chatId);

                    $telegramController->handleCallbackQuery($callbackQuery, $user, $chatId);
                }

                if ($message = $update->getMessage()) {
                    $telegramController->handleMessage($message, $update);
                }
            }

            sleep(1);
        }
    }
}
