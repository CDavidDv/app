<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
| Enlaces secretos: cambian el token por una sesión y redirigen a una URL limpia.
*/
Route::middleware('throttle:links')->group(function () {
    Route::get('/invitacion/{token}', [AccessController::class, 'invite']);
    Route::get('/mi-recuerdo/{token}', [AccessController::class, 'mine']);
    Route::get('/para-ti/{token}', [AccessController::class, 'birthday'])->where('token', '[A-Za-z0-9]{20,100}');
});

/*
| API (usa sesión + protección CSRF del grupo web).
*/
Route::prefix('api')->group(function () {
    Route::get('/state', [AccessController::class, 'state']);

    // Participantes
    Route::middleware('throttle:participate')->group(function () {
        Route::post('/participant', [ParticipantController::class, 'identify']);
        Route::get('/questions', [ParticipantController::class, 'questions']);
        Route::get('/my/contributions', [ParticipantController::class, 'contributions']);
        Route::post('/my/contributions', [ParticipantController::class, 'store']);
        Route::put('/my/contributions/{id}', [ParticipantController::class, 'update']);
        Route::delete('/my/contributions/{id}', [ParticipantController::class, 'destroy']);
        Route::delete('/my/media/{id}', [ParticipantController::class, 'destroyMedia']);
        Route::post('/uploads', [UploadController::class, 'start']);
        Route::post('/uploads/{id}/complete', [UploadController::class, 'complete']);
    });
    Route::post('/uploads/{id}/chunk', [UploadController::class, 'chunk'])->middleware('throttle:chunks');

    // Archivos (siempre autorizados)
    Route::get('/media/{id}/{variant?}', [MediaController::class, 'show'])
        ->where('variant', 'thumb')->middleware('throttle:media');

    // Cumpleañera
    Route::post('/birthday/unlock', [AccessController::class, 'birthdayPassword'])->middleware('throttle:6,1');
    Route::get('/birthday/memories', [BirthdayController::class, 'memories']);

    // Administración
    Route::prefix('admin')->group(function () {
        Route::post('/login', [Admin\AuthController::class, 'login'])->middleware('throttle:login');

        Route::middleware('auth')->group(function () {
            Route::post('/logout', [Admin\AuthController::class, 'logout']);
            Route::put('/password', [Admin\AuthController::class, 'password']);

            Route::get('/overview', [Admin\ContentController::class, 'overview']);
            Route::get('/contributions', [Admin\ContentController::class, 'contributions']);
            Route::patch('/contributions/{id}', [Admin\ContentController::class, 'updateContribution']);
            Route::delete('/contributions/{id}', [Admin\ContentController::class, 'destroyContribution']);
            Route::get('/participants', [Admin\ContentController::class, 'participants']);
            Route::patch('/participants/{id}', [Admin\ContentController::class, 'updateParticipant']);
            Route::delete('/participants/{id}', [Admin\ContentController::class, 'destroyParticipant']);
            Route::get('/media/{id}/download', [Admin\ContentController::class, 'download']);
            Route::get('/export/{participantId?}', [Admin\ExportController::class, 'zip']);

            Route::get('/guests', [Admin\GuestController::class, 'index']);
            Route::post('/guests', [Admin\GuestController::class, 'store']);
            Route::put('/guests/{guest}', [Admin\GuestController::class, 'update']);
            Route::delete('/guests/{guest}', [Admin\GuestController::class, 'destroy']);
            Route::post('/guests/{guest}/link', [Admin\GuestController::class, 'link']);

            Route::get('/settings', [Admin\SettingsController::class, 'show']);
            Route::put('/settings', [Admin\SettingsController::class, 'update']);
            Route::post('/settings/cover', [Admin\SettingsController::class, 'uploadCover']);
            Route::post('/settings/music', [Admin\SettingsController::class, 'uploadMusic']);
            Route::delete('/settings/{what}', [Admin\SettingsController::class, 'removeMedia'])->whereIn('what', ['cover', 'music']);
            Route::get('/links', [Admin\SettingsController::class, 'links']);
            Route::post('/links/{type}/regenerate', [Admin\SettingsController::class, 'regenerate']);
        });
    });

    Route::any('/{any}', fn () => response()->json(['message' => 'No encontrado.'], 404))->where('any', '.*');
});

/*
| Aplicación Vue (SPA). Todas las demás rutas.
*/
Route::view('/{any?}', 'app')->where('any', '^(?!api/|build/).*$');
