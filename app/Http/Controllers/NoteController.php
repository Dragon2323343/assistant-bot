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
        try {
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
                $date = $this->validateDate($text, $chatId);

                if (!$date) {
                    return;
                }

                $tempData = json_decode($user->temp_data, true) ?? [];
                $tempData['datetime'] = $date;
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

            if ($user->current_action === 'editing_note' && $user->current_action_step === 'waiting_note_text') {
                $this->updateNoteText($user, $chatId, $text);
            }

            if ($user->current_action === 'editing_note' && $user->current_action_step === 'waiting_note_date') {
                $this->updateNoteDate($user, $chatId, $text);
            }

            if ($user->current_action === 'editing_note' && $user->current_action_step === 'waiting_note_file') {
                $this->updateNoteFile($user, $chatId, $message);
            }
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleUserMessage: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    public function handleNoteCallback(User $user, int $chatId, string $callbackData, $callbackQuery)
    {
        try {
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
                    $this->showNoteDetail($user, $chatId, $param, $messageId);
                    break;

                case 'delete_note':
                    $this->deleteNote($user, $chatId, $param, $messageId);
                    break;

                case 'complete_note':
                    $this->toggleCompleteNote($user, $chatId, $param, $messageId);
                    break;

                case 'edit_note':
                    $this->startNoteEditing($user, $chatId, $param, $messageId);
                    break;

                case 'edit_note_text':
                    $this->askNewNoteText($user, $chatId, $param);
                    break;

                case 'edit_note_file':
                    $this->askNewNoteFile($user, $chatId, $param);
                    break;

                case 'edit_note_date':
                    $this->askNewNoteDate($user, $chatId, $param);
                    break;

                default:
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Неизвестное действие',
                    ]);
                    break;
            }
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleNoteCallback: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'callback_data' => $callbackData,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function handleSelectCategory(User $user, int $chatId, string $categoryId, int $messageId)
    {
        try {
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
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleSelectCategory: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function handleSaveNoteDraft(User $user, int $chatId)
    {
        try {
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
            $note->remind_datetime = $tempData['datetime']['date'] ?? null;
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
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleSaveNoteDraft: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function handleSetDatePrompt(User $user, int $chatId)
    {
        try {
            $user->current_action_step = 'waiting_note_datetime';
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Пожалуйста, введите дату и время для заметки в формате YYYY-MM-DD HH:MM (например, 2025-07-15 14:30):',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleSetDatePrompt: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function handleAttachFilePrompt(User $user, int $chatId)
    {
        try {
            $user->current_action_step = 'waiting_note_file';
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Пожалуйста, пришлите файл (документ, фото или голосовое сообщение), который хотите прикрепить к заметке.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleAttachFilePrompt: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function handleFile(User $user, int $chatId, string $fileId, string $type)
    {
        try {
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
        } catch (\Throwable $e) {
            \Log::error('Ошибка в handleFile: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function showNoteActionsMenu(int $chatId)
    {
        try {
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
        } catch (\Throwable $e) {
            \Log::error('Ошибка в showNoteActionsMenu: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function showNotesByCategory(User $user, int $chatId, int $categoryId, int $messageId)
    {
        try {
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
        } catch (\Throwable $e) {
            \Log::error('Ошибка в showNotesByCategory: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    public function showNoteDetail(User $user, int $chatId, int $noteId, int $messageId = null)
    {
        try {
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

            $status = $note->complete ? '✅ Выполнена' : '📝 Активна';
            $text .= "\n\nСтатус: *{$status}*";

            $inlineKeyboard = [
                [
                    ['text' => '✏️ Редактировать', 'callback_data' => "edit_note:{$note->id}"],
                    ['text' => '🗑 Удалить', 'callback_data' => "delete_note:{$note->id}"],
                ],
                [
                    ['text' => $note->complete ? '🔄 Отменить выполнение' : '✅ Отметить выполненной', 'callback_data' => "complete_note:{$note->id}"]
                ],
            ];

            if ($messageId) {
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $inlineKeyboard,
                    ]),
                ]);
            } else {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $inlineKeyboard,
                    ]),
                ]);
            }


            if ($note->file_path) {
                $file = InputFile::create($note->file_path);

                Telegram::sendDocument([
                    'chat_id' => $chatId,
                    'document' => $file,
                    'caption' => '📎 Прикреплённый файл',
                ]);
            }
        } catch (\Throwable $e) {
            \Log::error('Ошибка в showNoteDetail: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function deleteNote(User $user, int $chatId, int $noteId, int $messageId)
    {
        try {
            $note = $user->notes()->find($noteId);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Заметка не найдена или уже удалена.',
                ]);
                return;
            }

            if ($note->file_path && file_exists($note->file_path)) {
                unlink($note->file_path);
            }

            $note->delete();

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => '🗑 Заметка успешно удалена.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в showNoteDetail: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function toggleCompleteNote(User $user, int $chatId, int $noteId, int $messageId)
    {
        try {
            $note = $user->notes()->find($noteId);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❗Заметка не найдена.',
                ]);
                return;
            }

            $note->complete = !$note->complete;
            $note->save();

            $this->showNoteDetail($user, $chatId, $noteId, $messageId);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в toggleCompleteNote: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function startNoteEditing(User $user, int $chatId, int $noteId, int $messageId)
    {
        try {
            $note = $user->notes()->find($noteId);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Заметка не найдена.',
                ]);
                return;
            }

            $inlineKeyboard = [
                [
                    ['text' => '✏️ Изменить текст', 'callback_data' => "edit_note_text:{$note->id}"],
                    ['text' => '📎 Изменить файл', 'callback_data' => "edit_note_file:{$note->id}"],
                ],
                [
                    ['text' => '⏰ Изменить дату', 'callback_data' => "edit_note_date:{$note->id}"],
                ],
                [
                    ['text' => '🔙 Отмена', 'callback_data' => "show_note:{$note->id}"],
                ]
            ];

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => 'Что вы хотите изменить в заметке?',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $inlineKeyboard
                ]),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в startNoteEditing: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function askNewNoteText(User $user, int $chatId, int $noteId)
    {
        try {
            $user->current_action = 'editing_note';
            $user->current_action_step = 'waiting_note_text';
            $user->temp_data = json_encode([
                'note_id' => $noteId
            ]);

            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '✏️ Отправьте новый текст для заметки.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в askNewNoteText: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function askNewNoteFile(User $user, int $chatId, int $noteId)
    {
        try {
            $user->current_action = 'editing_note';
            $user->current_action_step = 'waiting_note_file';
            $user->temp_data = json_encode([
                'note_id' => $noteId
            ]);

            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '📎 Отправьте новый файл для заметки.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в askNewNoteFile: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function askNewNoteDate(User $user, int $chatId, int $noteId)
    {
        try {
            $user->current_action = 'editing_note';
            $user->current_action_step = 'waiting_note_date';
            $user->temp_data = json_encode([
                'note_id' => $noteId
            ]);

            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '⏰ Отправьте новую дату и время в формате `YYYY-MM-DD HH:MM`, например: `2025-07-20 14:30`',
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в askNewNoteDate: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function updateNoteText(User $user, int $chatId, string $newText)
    {
        try {
            $tempData = json_decode($user->temp_data, true);

            $note = $user->notes()->find($tempData['note_id']);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❗️Заметка не найдена.',
                ]);
                return;
            }

            $note->content = $newText;
            $note->save();

            $user->current_action = null;
            $user->current_action_step = null;
            $user->temp_data = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "✏️ Текст заметки обновлён.",
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в updateNoteText: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function updateNoteDate(User $user, int $chatId, string $newDate)
    {
        try {
            $tempData = json_decode($user->temp_data, true);

            $note = $user->notes()->find($tempData['note_id']);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❗️Заметка не найдена.',
                ]);
                return;
            }

            $newDate = $this->validateDate($newDate, $chatId);

            if (!$newDate) {
                return;
            }

            $note->remind_datetime = $newDate;
            $note->save();

            $user->current_action = null;
            $user->current_action_step = null;
            $user->temp_data = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "📅 Дата и время напоминания обновлены.",
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в updateNoteDate: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function updateNoteFile(User $user, int $chatId, $message)
    {
        try {
            $tempData = json_decode($user->temp_data, true);

            $note = $user->notes()->find($tempData['note_id']);

            if (!$note) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❗️Заметка не найдена.',
                ]);
                return;
            }

            $fileId = null;

            if ($document = $message->getDocument()) {
                $fileId = $document->getFileId();
            }

            if ($photo = $message->photo) {
                $largestPhotoIndex = count($photo) - 1;
                $fileId = $photo[$largestPhotoIndex]['file_id'];
            }

            if ($voice = $message->getVoice()) {
                $fileId = $voice->getFileId();
            }

            if (!$fileId) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❗️Пожалуйста, прикрепите файл, фото или голосовое сообщение.',
                ]);
                return;
            }

            $file = Telegram::getFile(['file_id' => $fileId]);
            $filePath = $file->getFilePath();

            $downloadUrl = "https://api.telegram.org/file/bot" . env('TELEGRAM_BOT_TOKEN') . "/" . $filePath;

            if ($note->file_path && str_starts_with($note->file_path, '/')) {
                if (file_exists($note->file_path)) {
                    unlink($note->file_path);
                }
            }

            $note->file_path = $downloadUrl;
            $note->save();

            $user->current_action = null;
            $user->current_action_step = null;
            $user->temp_data = null;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '📎 Файл успешно прикреплён к заметке.',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Ошибка в updateNoteFile: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);
        }
    }

    private function validateDate(string $dateString, int $chatId): ?DateTime
    {
        try {
            $date = DateTime::createFromFormat('Y-m-d H:i', $dateString);
            $errors = DateTime::getLastErrors();

            if (!$date || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '❌ Некорректный формат даты и времени. Пожалуйста, введите в формате *YYYY-MM-DD HH:MM*.',
                    'parse_mode' => 'Markdown',
                ]);
                return null;
            }

            if ($date < new DateTime()) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => '⏳ Нельзя установить напоминание в прошлом. Введите будущую дату и время.',
                ]);
                return null;
            }

            return $date;
        } catch (\Throwable $e) {
            \Log::error('Ошибка в validateDate: ' . $e->getMessage(), [
                'chat_id' => $chatId,
                'trace' => $e->getTraceAsString(),
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке вашего запроса. Пожалуйста, попробуйте позже.',
            ]);

            return null;
        }
    }
}
