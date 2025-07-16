<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'username',
        'current_action',
        'current_action_step',
        'temp_data',
    ];

    public static function createUser($telegramUser)
    {
        $user = self::getUserByTelegram($telegramUser);

        if ($user) {
            return $user;
        }

        return self::create([
            'telegram_user_id' => $telegramUser->getId(),
            'username'         => $telegramUser->getUsername(),
        ]);
    }

    public static function getUserByTelegram($telegramUser)
    {
        return self::firstOrCreate(
            ['telegram_user_id' => $telegramUser->getId()]
        );
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }
}
