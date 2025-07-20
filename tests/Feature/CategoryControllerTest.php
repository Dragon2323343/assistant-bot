<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Http\Controllers\CategoryController;
use Tests\TestCase;
use Telegram\Bot\Laravel\Facades\Telegram;

class CategoryControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Category::whereHas('user', function ($query) {
            $query->where('telegram_user_id', 123456);
        })->delete();

        User::where('telegram_user_id', 123456)->delete();

        parent::tearDown();
    }

    public function test_create_category_creates_category_and_sends_message()
    {
        Telegram::shouldReceive('sendMessage')->once()->with([
            'chat_id' => 123456,
            'text' => 'Категория TestCategory успешно создана!',
        ]);

        $user = User::create([
            'telegram_user_id' => 123456,
        ]);

        $controller = app(CategoryController::class);

        $controller->createCategory($user, 123456, 'TestCategory');

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'TestCategory',
        ]);

        $this->assertNull($user->fresh()->current_action);
    }

    public function test_handle_category_callback_deletes_category()
    {
        $user = User::create([
            'telegram_user_id' => 123456,
        ]);

        $category = Category::create([
            'user_id' => $user->id,
            'name' => 'Sample Category',
        ]);

        $callbackData = "delete_category:{$category->id}";

        $callbackQueryMock = \Mockery::mock(\Telegram\Bot\Objects\CallbackQuery::class);

        $messageMock = \Mockery::mock(\Telegram\Bot\Objects\Message::class);
        $messageMock->shouldReceive('getMessageId')->andReturn(42);

        $callbackQueryMock->shouldReceive('getMessage')->andReturn($messageMock);

        Telegram::shouldReceive('sendMessage')->once();
        Telegram::shouldReceive('editMessageText')->once();

        $controller = app(CategoryController::class);

        $controller->handleCategoryCallback($user, 123456, $callbackData, $callbackQueryMock);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_show_categories_sends_message_if_empty()
    {
        $user = User::create([
            'telegram_user_id' => 123456,
        ]);

        Telegram::shouldReceive('sendMessage')->once()->with([
            'chat_id' => 123456,
            'text' => 'У вас ещё нет категорий',
        ]);

        $controller = app(CategoryController::class);

        $controller->showCategories($user, 123456);

        $this->assertTrue(true);
    }

    public function test_show_categories_with_pagination()
    {
        $user = User::create([
            'telegram_user_id' => 123456,
        ]);

        for ($i = 0; $i < 10; $i++) {
            Category::create([
                'user_id' => $user->id,
                'name' => "Category {$i}",
            ]);
        }

        Telegram::shouldReceive('sendMessage')->once()->with(\Mockery::on(function ($arg) {
            return str_contains($arg['text'], 'Выберите категорию')
                && isset($arg['reply_markup']);
        }));

        $controller = app(CategoryController::class);

        $controller->showCategories($user, 123456, 1, null);

        $this->assertTrue(true);
    }
}
