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
    'edit_note'                  => [NoteController::class, 'handleNoteCallback'],
    'edit_note_text'             => [NoteController::class, 'handleNoteCallback'],
    'edit_note_file'             => [NoteController::class, 'handleNoteCallback'],
    'edit_note_date'             => [NoteController::class, 'handleNoteCallback'],
    'delete_note'                => [NoteController::class, 'handleNoteCallback'],
    'complete_note'              => [NoteController::class, 'handleNoteCallback'],
    'set_date_for_note_draft'    => [NoteController::class, 'handleNoteCallback'],
    'save_note_draft'            => [NoteController::class, 'handleNoteCallback'],
    'attach_file_for_note_draft' => [NoteController::class, 'handleNoteCallback'],

    'select_category' => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
    'edit_category'   => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
    'delete_category' => [App\Http\Controllers\CategoryController::class, 'handleCategoryCallback'],
];
