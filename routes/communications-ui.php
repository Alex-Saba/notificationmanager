<?php

use Acl\Communications\Http\Controllers\NotificationController;
use Acl\Communications\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/templates', [TemplateController::class, 'templatesPage'])->name('templates.page');
Route::get('/templates/create', [TemplateController::class, 'createPage'])->name('templates.create.page');
Route::get('/templates/{template}/edit', [TemplateController::class, 'editPage'])->whereNumber('template')->name('templates.edit.page');

Route::prefix('api')->as('api.')->group(function () {
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->whereNumber('template')->name('templates.show');
    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->whereNumber('template')->name('templates.update');
    Route::patch('/templates/{template}/rule', [TemplateController::class, 'updateRule'])->whereNumber('template')->name('templates.rule.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::get('/notifications/{communication}', [NotificationController::class, 'show'])->whereNumber('communication')->name('notifications.show');
    Route::patch('/notifications/{communication}/read', [NotificationController::class, 'markRead'])->whereNumber('communication')->name('notifications.read');
    Route::patch('/notifications/{communication}/unread', [NotificationController::class, 'markUnread'])->whereNumber('communication')->name('notifications.unread');
    Route::delete('/notifications/{communication}', [NotificationController::class, 'destroy'])->whereNumber('communication')->name('notifications.destroy');
});

Route::get('/notifications', [NotificationController::class, 'page'])->name('notifications.page');
