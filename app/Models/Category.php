<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['user_id', 'name'];

    public static function createCategory($user, string $categoryName)
    {
        return self::create([
            'user_id' => $user->id,
            'name' => $categoryName,
        ]);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
