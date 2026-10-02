<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\SettlementController;
use App\Http\Middleware\RequireSpaSession;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['data' => ['status' => 'ok']]);

Route::prefix('auth')->middleware(['throttle:10,1', RequireSpaSession::class])->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
});
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::patch('auth/password', [AuthController::class, 'password'])->middleware('throttle:10,1')->name('auth.password');
    Route::apiResource('groups', GroupController::class);
    Route::post('groups/{group}/invitation', [InvitationController::class, 'store']);
    Route::delete('groups/{group}/invitation', [InvitationController::class, 'destroy']);
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:30,1');
    Route::post('invitations/{token}/join', [InvitationController::class, 'join'])->middleware('throttle:10,1');
    Route::get('groups/{group}/members', [GroupController::class, 'members']);
    Route::post('groups/{group}/members', [GroupController::class, 'addMember']);
    Route::delete('groups/{group}/members/{user}', [GroupController::class, 'removeMember']);
    Route::apiResource('groups.expenses', ExpenseController::class);
    Route::get('groups/{group}/settlements', [SettlementController::class, 'show']);
    Route::get('groups/{group}/summary', [SettlementController::class, 'summary']);
});
