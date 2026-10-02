<?php

use App\Http\Controllers\Admin\AccountSecurityController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ContractorController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\JobController as AdminJobController;
use App\Http\Controllers\Admin\NotificationDeliveryController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Contractor\JobController as ContractorJobController;
use App\Http\Controllers\Contractor\ProfileController;
use App\Http\Controllers\ContractorDocumentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\JobMediaController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ShellController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShellController::class, 'home'])->name('home');
Route::get('/health', HealthController::class)->name('health');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:identity-login')->name('login.store');
    Route::get('/register', [RegistrationController::class, 'create'])->middleware('throttle:identity-form')->name('register');
    Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:identity-registration')->name('register.store');
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->middleware('throttle:identity-recovery')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:identity-reset')->name('password.update');
});
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'account.current'])->group(function (): void {
    Route::get('/dashboard', [ShellController::class, 'dashboard'])->name('dashboard');
    Route::get('/account/status', [VerificationController::class, 'notice'])->name('account.status');
    Route::get('/account/settings', [VerificationController::class, 'settings'])->name('account.settings');
    Route::get('/verify-email', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:identity-verification-send')->name('verification.send');
    Route::get('/verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:identity-verify'])->name('verification.verify');
    Route::middleware('verified')->group(function (): void {
        Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/bulk', [NotificationCenterController::class, 'bulk'])->middleware('throttle:domain-write')->name('notifications.bulk');
        Route::get('/notifications/preferences', [NotificationCenterController::class, 'preferences'])->name('notifications.preferences');
        Route::put('/notifications/preferences', [NotificationCenterController::class, 'updatePreferences'])->middleware('throttle:domain-write')->name('notifications.preferences.update');
        Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'read'])->middleware('throttle:domain-write')->name('notifications.read');
    });
    Route::middleware(['role:admin', 'verified'])->group(function (): void {
        Route::get('/admin', [ShellController::class, 'admin'])->name('admin.dashboard');
        Route::get('/admin/notification-deliveries', [NotificationDeliveryController::class, 'index'])->name('admin.notification-deliveries.index');
        Route::post('/admin/notification-deliveries/{delivery}/retry', [NotificationDeliveryController::class, 'retry'])->middleware('throttle:domain-write')->name('admin.notification-deliveries.retry');
        Route::get('/admin/review', [ReviewController::class, 'index'])->name('admin.review.index');
        Route::post('/admin/review', [ReviewController::class, 'bulk'])->middleware('throttle:domain-write')->name('admin.review.bulk');
        Route::get('/admin/contractors/print', [ReviewController::class, 'print'])->name('admin.contractors.print');
        Route::get('/admin/documents', [DocumentController::class, 'index'])->name('admin.documents.index');
        Route::get('/admin/activity', [ActivityLogController::class, 'index'])->name('admin.activity.index');
        Route::get('/admin/activity/print', [ActivityLogController::class, 'print'])->name('admin.activity.print');
        Route::get('/admin/templates', [TemplateController::class, 'index'])->name('admin.templates.index');
        Route::get('/admin/templates/create', [TemplateController::class, 'create'])->name('admin.templates.create');
        Route::post('/admin/templates', [TemplateController::class, 'store'])->name('admin.templates.store');
        Route::get('/admin/templates/{template}/edit', [TemplateController::class, 'edit'])->name('admin.templates.edit');
        Route::put('/admin/templates/{template}', [TemplateController::class, 'update'])->name('admin.templates.update');
        Route::delete('/admin/templates/{template}', [TemplateController::class, 'destroy'])->name('admin.templates.destroy');
        Route::get('/admin/templates/{template}/content', [TemplateController::class, 'content'])->name('admin.templates.content');
        Route::get('/admin/accounts', [AccountSecurityController::class, 'index'])->name('admin.accounts.index');
        Route::get('/admin/accounts/{user}/password', [AccountSecurityController::class, 'edit'])->name('admin.accounts.password');
        Route::post('/admin/accounts/{user}/password', [AccountSecurityController::class, 'update'])->middleware('throttle:identity-admin-password')->name('admin.accounts.password.update');

        Route::get('/admin/contractors', [ContractorController::class, 'index'])->name('admin.contractors.index');
        Route::get('/admin/contractors/create', [ContractorController::class, 'create'])->name('admin.contractors.create');
        Route::post('/admin/contractors', [ContractorController::class, 'store'])->name('admin.contractors.store');
        Route::get('/admin/contractors/{user}', [ContractorController::class, 'show'])->name('admin.contractors.show');
        Route::get('/admin/contractors/{user}/edit', [ContractorController::class, 'edit'])->name('admin.contractors.edit');
        Route::put('/admin/contractors/{user}', [ContractorController::class, 'update'])->middleware('throttle:domain-write')->name('admin.contractors.update');
        Route::post('/admin/contractors/{user}/approval', [ContractorController::class, 'approval'])->middleware('throttle:domain-write')->name('admin.contractors.approval');
        Route::post('/admin/contractors/{user}/block', [ContractorController::class, 'block'])->middleware('throttle:domain-write')->name('admin.contractors.block');
        Route::post('/admin/contractors/{user}/priority', [ContractorController::class, 'priority'])->name('admin.contractors.priority');
        Route::post('/admin/contractors/{user}/archive', [ContractorController::class, 'archive'])->name('admin.contractors.archive');
        Route::delete('/admin/contractors/{user}', [ContractorController::class, 'destroy'])->name('admin.contractors.destroy');
        Route::post('/admin/contractors/{user}/restore', [ContractorController::class, 'restore'])->name('admin.contractors.restore');
        Route::post('/admin/contractors/{user}/notes', [ContractorController::class, 'note'])->name('admin.contractors.notes.store');
        Route::delete('/admin/contractors/{user}/photo', [ContractorController::class, 'removePhoto'])->name('admin.contractors.photo.destroy');
        Route::get('/admin/contractors/{user}/documents', [ContractorDocumentController::class, 'adminIndex'])->name('admin.contractors.documents.index');
        Route::post('/admin/contractors/{user}/documents', [ContractorDocumentController::class, 'store'])->name('admin.contractors.documents.store');
        Route::post('/admin/contractors/{user}/documents/{document}/archive', [ContractorDocumentController::class, 'archive'])->name('admin.contractors.documents.archive');
        Route::delete('/admin/contractors/{user}/documents/{document}', [ContractorDocumentController::class, 'destroy'])->name('admin.contractors.documents.destroy');

        Route::get('/admin/services', [ServiceController::class, 'index'])->name('admin.services.index');
        Route::get('/admin/services/create', [ServiceController::class, 'create'])->name('admin.services.create');
        Route::post('/admin/services', [ServiceController::class, 'store'])->name('admin.services.store');
        Route::get('/admin/services/{service}/edit', [ServiceController::class, 'edit'])->name('admin.services.edit');
        Route::put('/admin/services/{service}', [ServiceController::class, 'update'])->name('admin.services.update');
        Route::delete('/admin/services/{service}', [ServiceController::class, 'destroy'])->name('admin.services.destroy');

        Route::get('/admin/jobs', [AdminJobController::class, 'index'])->name('admin.jobs.index');
        Route::get('/admin/jobs/create', [AdminJobController::class, 'create'])->name('admin.jobs.create');
        Route::post('/admin/jobs', [AdminJobController::class, 'store'])->middleware('throttle:domain-write')->name('admin.jobs.store');
        Route::get('/admin/jobs/{job}', [AdminJobController::class, 'show'])->name('admin.jobs.show');
        Route::get('/admin/jobs/{job}/edit', [AdminJobController::class, 'edit'])->name('admin.jobs.edit');
        Route::put('/admin/jobs/{job}', [AdminJobController::class, 'update'])->middleware('throttle:domain-write')->name('admin.jobs.update');
        Route::post('/admin/jobs/{job}/assignment', [AdminJobController::class, 'assignment'])->middleware('throttle:domain-write')->name('admin.jobs.assignment');
        Route::post('/admin/jobs/{job}/acceptance', [AdminJobController::class, 'acceptance'])->middleware('throttle:domain-write')->name('admin.jobs.acceptance');
        Route::post('/admin/jobs/{job}/completion', [AdminJobController::class, 'completion'])->middleware('throttle:domain-write')->name('admin.jobs.completion');
        Route::post('/admin/jobs/{job}/notes', [AdminJobController::class, 'note'])->middleware('throttle:domain-write')->name('admin.jobs.notes.store');
        Route::post('/admin/jobs/{job}/media', [AdminJobController::class, 'media'])->middleware('throttle:domain-write')->name('admin.jobs.media.store');
        Route::delete('/admin/jobs/{job}/media/{media}', [AdminJobController::class, 'destroyMedia'])->name('admin.jobs.media.destroy');
        Route::delete('/admin/jobs/{job}', [AdminJobController::class, 'destroy'])->name('admin.jobs.destroy');
        Route::get('/admin/jobs/{job}/print', [AdminJobController::class, 'print'])->name('admin.jobs.print');
    });
    Route::middleware(['role:contractor', 'verified'])->group(function (): void {
        Route::get('/contractor', [ShellController::class, 'contractor'])->name('contractor.dashboard');
        Route::get('/contractor/jobs', [ContractorJobController::class, 'index'])->name('contractor.jobs.index');
        Route::get('/contractor/jobs/{job}', [ContractorJobController::class, 'show'])->name('contractor.jobs.show');
        Route::post('/contractor/jobs/{job}/acceptance', [ContractorJobController::class, 'accept'])->middleware('throttle:domain-write')->name('contractor.jobs.acceptance');
        Route::post('/contractor/jobs/{job}/notes', [ContractorJobController::class, 'note'])->middleware('throttle:domain-write')->name('contractor.jobs.notes.store');
        Route::post('/contractor/jobs/{job}/media', [ContractorJobController::class, 'media'])->middleware('throttle:domain-write')->name('contractor.jobs.media.store');
        Route::get('/contractor/jobs/{job}/print', [ContractorJobController::class, 'print'])->name('contractor.jobs.print');
        Route::get('/contractor/{user}/profile', [ProfileController::class, 'edit'])->name('contractor.profile.edit');
        Route::put('/contractor/{user}/profile', [ProfileController::class, 'update'])->middleware('throttle:domain-write')->name('contractor.profile.update');
        Route::delete('/contractor/{user}/profile/photo', [ProfileController::class, 'removePhoto'])->middleware('throttle:domain-write')->name('contractor.profile.photo.destroy');
        Route::get('/contractor/{user}/documents', [ContractorDocumentController::class, 'contractorIndex'])->name('contractor.documents.index');
        Route::post('/contractor/{user}/documents', [ContractorDocumentController::class, 'store'])->name('contractor.documents.store');
    });
    Route::get('/documents/{document}/download', [ContractorDocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview', [ContractorDocumentController::class, 'preview'])->name('documents.preview');
    Route::get('/users/{user}/profile-photo', ProfilePhotoController::class)->name('users.profile-photo');
    Route::get('/job-media/{media}/preview', [JobMediaController::class, 'preview'])->name('job-media.preview');
    Route::get('/job-media/{media}/download', [JobMediaController::class, 'download'])->name('job-media.download');
    Route::get('/job-media/{media}/thumbnail', [JobMediaController::class, 'thumbnail'])->name('job-media.thumbnail');
});

Route::get('/local/preview/{role}', [ShellController::class, 'preview'])
    ->whereIn('role', ['admin', 'contractor'])
    ->middleware('local.preview')
    ->name('local.preview');
