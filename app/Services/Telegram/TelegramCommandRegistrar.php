<?php
namespace App\Services\Telegram;

use Telegram\Bot\Laravel\Facades\Telegram;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Commands\NewNoteCommand;
use App\Telegram\Commands\NewCategoryCommand;
use App\Telegram\Commands\ListCategoriesCommand;
use App\Telegram\Commands\ListNotesCommand;

class TelegramCommandRegistrar
{
    protected static $commandsRegistered = false;

    public static function registerCommands()
    {
        if (self::$commandsRegistered) {
            return;
        }

        Telegram::addCommand(StartCommand::class);
        Telegram::addCommand(NewNoteCommand::class);
        Telegram::addCommand(NewCategoryCommand::class);
        Telegram::addCommand(ListCategoriesCommand::class);
        Telegram::addCommand(ListNotesCommand::class);

        Telegram::setMyCommands([
            'commands' => [
                ['command' => 'start', 'description' => 'Начальное приветствие'],
                ['command' => 'new_category', 'description' => 'Создать новую категорию'],
                ['command' => 'new_note', 'description' => 'Создать новую заметку'],
                ['command' => 'list_notes', 'description' => 'Показать список заметок'],
                ['command' => 'list_categories', 'description' => 'Показать список категорий'],
            ],
        ]);

        self::$commandsRegistered = true;
    }
}
