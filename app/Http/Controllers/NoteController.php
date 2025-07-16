<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\User;
use DateTime;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Laravel\Facades\Telegram;

class NoteController extends Controller
{
    public function handleUserMessage(User $user, int $chatId, $message)
    {
        $text = trim($message->getText());

        if ($user->current_action === 'creating_note' && $user->current_action_step === 'waiting_note_text') {

            $tempData = json_decode($user->temp_data, true) ?? [];

            $tempData['note_text'] = $text;

            $user->temp_data = json_encode($tempData);
            $user->current_action_step = 'waiting_note_action';
            $user->save();

            $this->showNoteActionsMenu($chatId);
        }

        if ($user->current_action === 'creating_note' && $user->current_action_step === 'waiting_note_datetime') {
            $date = DateTime::createFromFormat('Y-m-d H:i', $text);
            $errors = DateTime::getLastErrors();

            if (!$date || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Некорректный формат даты и времени. Пожалуйста, введите в формате YYYY-MM-DD HH:MM.',
                ]);
                return;
            }

            $tempData = json_decode($user->temp_data, true) ?? [];
            $tempData['datetime'] = $text;
            $user->temp_data = json_encode($tempData);

            $user->current_action_step = 'note_ready_to_save';
            $user->save();

            $this->showNoteActionsMenu($chatId);
        }

        if ($user->current_action === 'creating_note' && $user->current_action_step === 'waiting_note_file') {

            if ($document = $message->getDocument()) {
                $fileId = $document->getFileId();
                $this->handleFile($user, $chatId, $fileId, 'document');
                return response('ok', 200);
            }

            if ($photo = $message->photo) {
                $largestPhotoIndex = count($photo) - 1;

                $fileId = $photo[$largestPhotoIndex]['file_id'];
                $this->handleFile($user, $chatId, $fileId, 'photo');

                return response('ok', 200);
            }

            if ($voice = $message->getVoice()) {
                $fileId = $voice->getFileId();
                $this->handleFile($user, $chatId, $fileId, 'voice');
                return response('ok', 200);
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Пожалуйста, пришлите именно файл (документ, фото или голосовое сообщение).',
            ]);
            return response('ok', 200);
        }
    }

    public function handleNoteCallback(User $user, int $chatId, string $callbackData, $callbackQuery)
    {
        $parts = explode(':', $callbackData, 2);
        $action = $parts[0];
        $param = $parts[1] ?? null;

        $messageId = $callbackQuery->getMessage()->getMessageId();

        switch ($action) {
            case 'select_category_for_note':
                $this->handleSelectCategory($user, $chatId, $param, $messageId);
                break;

            case 'set_date_for_note_draft':
                $this->handleSetDatePrompt($user, $chatId);
                break;

            case 'attach_file_for_note_draft':
                $this->handleAttachFilePrompt($user, $chatId);
                break;

            case 'save_note_draft':
                $this->handleSaveNoteDraft($user, $chatId);
                break;

            case 'show_notes_by_category':
                $this->showNotesByCategory($user, $chatId, $param, $messageId);
                break;

            case 'show_note':
                $this->showNoteDetail($user, $chatId, $param);
                break;

            default:
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Неизвестное действие',
                ]);
                break;
        }
    }

    private function handleSelectCategory(User $user, int $chatId, string $categoryId, int $messageId)
    {
        $category = $user->categories()->find($categoryId);

        if (!$category) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Категория не найдена. Попробуйте ещё раз.',
            ]);
            return;
        }

        $user->current_action = 'creating_note';
        $user->current_action_step = 'waiting_note_text';
        $user->temp_data = json_encode([
            'category_id' => $category->id,
        ]);
        $user->save();

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "Категория выбрана: *{$category->name}*\nТеперь введите текст заметки:",
            'parse_mode' => 'Markdown',
        ]);
    }

    private function handleSaveNoteDraft(User $user, int $chatId)
    {
        $tempData = json_decode($user->temp_data, true);

        if (empty($tempData['note_text']) || empty($tempData['category_id'])) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Ошибка: не хватает данных для сохранения заметки.',
            ]);
            return;
        }

        $note = new Note();
        $note->user_id = $user->id;
        $note->category_id = $tempData['category_id'];
        $note->content = $tempData['note_text'];
        $note->remind_datetime = $tempData['datetime'] ?? null;
        $note->file_path = $tempData['file_path'] ?? null;
        $note->save();

        // Сбрасываем действия пользователя
        $user->current_action = null;
        $user->current_action_step = null;
        $user->temp_data = null;
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Заметка успешно сохранена!',
        ]);
    }

    private function handleSetDatePrompt(User $user, int $chatId)
    {
        $user->current_action_step = 'waiting_note_datetime';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Пожалуйста, введите дату и время для заметки в формате YYYY-MM-DD HH:MM (например, 2025-07-15 14:30):',
        ]);
    }

    private function handleAttachFilePrompt(User $user, int $chatId)
    {
        $user->current_action_step = 'waiting_note_file';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Пожалуйста, пришлите файл (документ, фото или голосовое сообщение), который хотите прикрепить к заметке.',
        ]);
    }

    private function handleFile(User $user, int $chatId, string $fileId, string $type)
    {
        $file = Telegram::getFile(['file_id' => $fileId]);
        $filePath = $file->getFilePath();

        $downloadUrl = 'https://api.telegram.org/file/bot' . env('TELEGRAM_BOT_TOKEN') . '/' . $filePath;

        $tempData = json_decode($user->temp_data, true) ?? [];
        $tempData['file_path'] = $downloadUrl;
        $tempData['file_type'] = $type;

        $user->temp_data = json_encode($tempData);
        $user->current_action_step = 'waiting_note_action';
        $user->save();

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Файл успешно прикреплен к заметке.',
        ]);

        $this->showNoteActionsMenu($chatId);
    }

    private function showNoteActionsMenu(int $chatId)
    {
        $inlineKeyboard = [
            [
                ['text' => '✅ Сохранить', 'callback_data' => 'save_note_draft'],
            ],
            [
                ['text' => '📅 Установить дату и время', 'callback_data' => 'set_date_for_note_draft'],
            ],
            [
                ['text' => '📎 Прикрепить файл', 'callback_data' => 'attach_file_for_note_draft'],
            ],
        ];

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Выберите действие для заметки:',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    private function showNotesByCategory(User $user, int $chatId, int $categoryId, int $messageId)
    {
        $category = $user->categories()->find($categoryId);

        if (!$category) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Категория не найдена.',
            ]);
            return;
        }

        $notes = $category->notes()->get();

        if ($notes->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "В категории *{$category->name}* заметок пока нет",
                'parse_mode' => 'Markdown',
            ]);
            return;
        }

        $inlineKeyboard = [];

        foreach ($notes as $note) {
            $preview = mb_strlen($note->content) > 40 ? mb_substr($note->content, 0, 40) . '...' : $note->content;

            $inlineKeyboard[] = [[
                'text' => $preview,
                'callback_data' => 'show_note:' . $note->id,
            ]];
        }

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "Заметки из категории *{$category->name}:*",
            'parse_mode' => 'Markdown',
            'reply_markup' => json_encode(['inline_keyboard' => $inlineKeyboard]),
        ]);
    }

    private function showNoteDetail(User $user, int $chatId, int $noteId)
    {
        $note = $user->notes()->find($noteId);

        if (!$note) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Заметка не найдена.',
            ]);
            return;
        }

        $text = "*Заметка:*\n\n" . $note->content;

        if ($note->remind_datetime) {
            $date = (new \DateTime($note->remind_datetime))->format('Y-m-d H:i');
            $text .= "\n\n⏰ Напоминание на: *{$date}*";
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);

        if ($note->file_path) {
            $file = InputFile::create($note->file_path);

            Telegram::sendDocument([
                'chat_id' => $chatId,
                'document' => $file,
                'caption' => '📎 Прикреплённый файл',
            ]);
        }
    }
}
