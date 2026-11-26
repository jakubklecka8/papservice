<?php

use App\Http\Controllers\Api\V1\Admin\MailController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PlanningController;
use App\Http\Controllers\Api\V1\ProtocolController;
use App\Http\Controllers\Api\V1\PushController;
use App\Http\Controllers\Api\V1\RouteController;
use App\Http\Controllers\Api\V1\SetupController;
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
        Route::post('admin/mailboxes/{mailbox}/reprocess', [MailController::class, 'reprocess']);
        Route::get('admin/ai', [MailController::class, 'aiSettings']);
        Route::put('admin/ai', [MailController::class, 'saveAiSettings']);
        Route::post('admin/ai/test', [MailController::class, 'testAi']);
        Route::get('emails', [MailController::class, 'emails']);
        Route::get('emails/{email}', [MailController::class, 'email']);
        Route::post('emails/{email}/action', [MailController::class, 'emailAction']);

        Route::get('devices', [DeviceController::class, 'index']);
        Route::get('devices/export', [DeviceController::class, 'export']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::get('devices/{device}', [DeviceController::class, 'show']);
        Route::patch('devices/{device}', [DeviceController::class, 'update']);
        Route::post('devices/{device}/events', [DeviceController::class, 'addEvent']);

        Route::get('routes', [RouteController::class, 'show']);
        Route::post('routes/optimize', [RouteController::class, 'optimize']);
        Route::post('routes/geocode', [RouteController::class, 'geocode']);
        Route::get('planning', [PlanningController::class, 'calendar']);
        Route::post('tickets/{ticket}/plan', [PlanningController::class, 'plan']);
        Route::post('absences', [PlanningController::class, 'storeAbsence']);
        Route::delete('absences/{absence}', [PlanningController::class, 'destroyAbsence']);
        Route::get('schedules', [PlanningController::class, 'schedules']);
        Route::post('schedules', [PlanningController::class, 'storeSchedule']);
        Route::patch('schedules/{schedule}', [PlanningController::class, 'updateSchedule']);
        Route::delete('schedules/{schedule}', [PlanningController::class, 'destroySchedule']);
        Route::post('schedules/{schedule}/run', [PlanningController::class, 'runSchedule']);

        Route::get('admin/projects', [SetupController::class, 'projects']);
        Route::post('admin/projects', [SetupController::class, 'storeProject']);
        Route::patch('admin/projects/{project}', [SetupController::class, 'updateProject']);
        Route::get('checklists', [SetupController::class, 'checklists']);
        Route::post('checklists', [SetupController::class, 'storeChecklist']);
        Route::patch('checklists/{checklist}', [SetupController::class, 'updateChecklist']);
        Route::delete('checklists/{checklist}', [SetupController::class, 'destroyChecklist']);

        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::post('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
        Route::post('expenses/{expense}/action', [ExpenseController::class, 'action']);
        Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt']);

        Route::get('pay-rates', [PayrollController::class, 'rates']);
        Route::post('pay-rates', [PayrollController::class, 'storeRate']);
        Route::patch('pay-rates/{rate}', [PayrollController::class, 'updateRate']);
        Route::delete('pay-rates/{rate}', [PayrollController::class, 'destroyRate']);
        Route::get('payroll', [PayrollController::class, 'overview']);
        Route::get('payroll/statement', [PayrollController::class, 'statement']);
        Route::get('payroll/export', [PayrollController::class, 'export']);
        Route::post('payroll/confirm', [PayrollController::class, 'confirm']);
        Route::post('payouts/{payout}/pay', [PayrollController::class, 'pay']);
        Route::delete('payouts/{payout}', [PayrollController::class, 'unconfirm']);
        Route::get('project-costs', [PayrollController::class, 'projects']);

        Route::get('tariffs', [BillingController::class, 'tariffs']);
        Route::post('tariffs', [BillingController::class, 'storeTariff']);
        Route::patch('tariffs/{tariff}', [BillingController::class, 'updateTariff']);
        Route::delete('tariffs/{tariff}', [BillingController::class, 'destroyTariff']);
        Route::patch('tickets/{ticket}/price', [BillingController::class, 'updateTicketPrice']);
        Route::get('billing', [BillingController::class, 'report']);
        Route::get('billing/export', [BillingController::class, 'export']);

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
        Route::get('protocols/{protocol}/pdf', [ProtocolController::class, 'pdf']);
        Route::post('protocols/{protocol}/approve', [ProtocolController::class, 'approve']);

        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::patch('attachments/{attachment}', [AttachmentController::class, 'update']);
    });
});
