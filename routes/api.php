<?php

use App\Http\Controllers\Api\V1\Admin\MailController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ProtocolController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('push/key', [PushController::class, 'key']);
        Route::post('push/subscribe', [PushController::class, 'subscribe']);
        Route::post('push/unsubscribe', [PushController::class, 'unsubscribe']);
        Route::post('push/test', [PushController::class, 'test']);

        Route::get('projects', [LookupController::class, 'projects']);
        Route::get('locations', [LookupController::class, 'locations']);
        Route::get('technicians', [LookupController::class, 'technicians']);

        Route::post('locations', [LocationController::class, 'store']);
        Route::patch('locations/{location}', [LocationController::class, 'update']);

        Route::get('admin/users', [UserController::class, 'index']);
        Route::post('admin/users', [UserController::class, 'store']);
        Route::patch('admin/users/{user}', [UserController::class, 'update']);

        Route::get('warehouses', [StockController::class, 'warehouses']);
        Route::post('warehouses', [StockController::class, 'storeWarehouse']);
        Route::patch('warehouses/{warehouse}', [StockController::class, 'updateWarehouse']);
        Route::get('parts', [StockController::class, 'parts']);
        Route::post('parts', [StockController::class, 'storePart']);
        Route::patch('parts/{part}', [StockController::class, 'updatePart']);
        Route::get('stock', [StockController::class, 'levels']);
        Route::get('stock/movements', [StockController::class, 'movements']);
        Route::post('stock/movements', [StockController::class, 'storeMovement']);
        Route::put('stock/minimums', [StockController::class, 'setMinimum']);
        Route::get('stock/reorder', [StockController::class, 'reorder']);

        Route::get('admin/mailboxes', [MailController::class, 'mailboxes']);
        Route::post('admin/mailboxes', [MailController::class, 'storeMailbox']);
        Route::patch('admin/mailboxes/{mailbox}', [MailController::class, 'updateMailbox']);
        Route::delete('admin/mailboxes/{mailbox}', [MailController::class, 'destroyMailbox']);
        Route::post('admin/mailboxes/{mailbox}/test', [MailController::class, 'testMailbox']);
        Route::post('admin/mailboxes/{mailbox}/fetch', [MailController::class, 'fetchNow']);
        Route::get('admin/ai', [MailController::class, 'aiSettings']);
        Route::put('admin/ai', [MailController::class, 'saveAiSettings']);
        Route::post('admin/ai/test', [MailController::class, 'testAi']);
        Route::get('emails', [MailController::class, 'emails']);
        Route::get('emails/{email}', [MailController::class, 'email']);
        Route::post('emails/{email}/action', [MailController::class, 'emailAction']);

        Route::get('tickets/summary', [TicketController::class, 'summary']);
        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show']);
        Route::patch('tickets/{ticket}', [TicketController::class, 'update']);
        Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::post('tickets/{ticket}/status', [TicketController::class, 'status']);
        Route::get('tickets/{ticket}/client-report', [TicketController::class, 'clientReportDraft']);
        Route::post('tickets/{ticket}/client-report', [TicketController::class, 'clientReportSend']);

        Route::put('tickets/{ticket}/visits/{uuid}', [VisitController::class, 'upsert']);
        Route::get('visits/{visit}', [VisitController::class, 'show']);
        Route::post('visits/{visit}/submit', [VisitController::class, 'submit']);
        Route::post('visits/{visit}/return', [VisitController::class, 'returnToTechnician']);

        Route::post('visits/{visit}/protocol', [ProtocolController::class, 'store']);
        Route::get('protocols/{protocol}', [ProtocolController::class, 'show']);
        Route::get('protocols/{protocol}/html', [ProtocolController::class, 'html']);
        Route::post('protocols/{protocol}/approve', [ProtocolController::class, 'approve']);

        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::patch('attachments/{attachment}', [AttachmentController::class, 'update']);
    });
});
