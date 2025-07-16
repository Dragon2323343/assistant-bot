<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NoteController;
use App\Services\Telegram\TelegramHandler;

return [
    'back_to_' => [TelegramHandler::class, 'handleCommonNavigation'],
    'page_'    => [TelegramHandler::class, 'handleCommonNavigation'],

    'select_category_for_note'   => [NoteController::class, 'handleNoteCallback'],
    'show_notes_by_category'     => [NoteController::class, 'handleNoteCallback'],
    'show_note'                  => [NoteController::class, 'handleNoteCallback'],
    'set_date_for_note_draft'    => [NoteController::class, 'handleNoteCallback'],
    'save_note_draft'            => [NoteController::class, 'handleNoteCallback'],
    'attach_file_for_note_draft' => [NoteController::class, 'handleNoteCallback'],

    'select_category' => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
    'edit_category'   => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
    'delete_category' => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
];
