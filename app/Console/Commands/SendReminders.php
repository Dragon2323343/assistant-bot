<?php

namespace App\Console\Commands;

use App\Http\Controllers\NoteController;
use App\Models\Note;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send reminders for tasks with remind_datetime';

    public function handle()
    {
        $now = Carbon::now('Europe/Moscow')->format('Y-m-d H:i');

        $notes = Note::whereRaw("DATE_FORMAT(remind_datetime, '%Y-%m-%d %H:%i') = ?", [$now])
            ->where('complete', 0)
            ->get();

        foreach ($notes as $note) {
            $this->notifyUser($note);
        }

        return 0;
    }

    protected function notifyUser(Note $note)
    {
        $user = $note->user;

        $controller = new NoteController();

        Telegram::sendMessage([
            'chat_id' => $user->chat_id,
            'text' => "🔔 Напоминание",
        ]);

        $controller->showNoteDetail($user, $user->chat_id, $note->id);
    }
}
