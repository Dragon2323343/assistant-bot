<?php

namespace App\Providers;

use App\Services\Telegram\TelegramHandler;
use App\Telegram\Commands\ListCategoriesCommand;
use App\Telegram\Commands\ListNotesCommand;
use App\Telegram\Commands\NewCategoryCommand;
use App\Telegram\Commands\NewNoteCommand;
use App\Telegram\Commands\StartCommand;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Laravel\Facades\Telegram;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            TelegramHandler::class,
            TelegramHandler::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!app()->environment('testing')) {
//            Telegram::addCommand(StartCommand::class);
//            Telegram::addCommand(NewNoteCommand::class);
//            Telegram::addCommand(NewCategoryCommand::class);
//            Telegram::addCommand(ListCategoriesCommand::class);
//            Telegram::addCommand(ListNotesCommand::class);
//
//            Telegram::setMyCommands([
//                'commands' => [
//                    ['command' => 'start', 'description' => 'Начальное приветствие'],
//                    ['command' => 'new_category', 'description' => 'Создать новую категорию'],
//                    ['command' => 'new_note', 'description' => 'Создать новую заметку'],
//                    ['command' => 'list_notes', 'description' => 'Показать список заметок'],
//                    ['command' => 'list_categories', 'description' => 'Показать список категорий']
//                ],
//            ]);
        }
    }
}
