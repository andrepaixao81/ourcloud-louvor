<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActionCenterController;
use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\AdminAssistanceController;
use App\Http\Controllers\AdminMinisterEligibilityController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CongregationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\ExecutionController;
use App\Http\Controllers\GoogleIntegrationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\RepertoireController;
use App\Http\Controllers\RehearsalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceTypeController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SongHistoryController;
use App\Http\Controllers\SwapController;
use App\Http\Controllers\SpecialEventController;
use App\Http\Controllers\WhatsappSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'update'])->name('password.update');
});

Route::get('/calendar/feed/{token}.ics', [CalendarController::class, 'feed'])->middleware('throttle:60,1')->name('calendar.feed');
Route::get('/calendar/team-feed/{token}.ics', [CalendarController::class, 'teamFeed'])->middleware('throttle:60,1')->name('calendar.team-feed');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/change-password-required', [PasswordController::class, 'required'])->name('password.required');
    Route::post('/change-password-required', [PasswordController::class, 'requiredUpdate'])->name('password.required.update');
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::patch('/account/email', [AccountController::class, 'updateEmail'])->name('account.email.update');
    Route::patch('/account/phone', [AccountController::class, 'updatePhone'])->name('account.phone.update');
    Route::patch('/account/birth-date', [AccountController::class, 'updateBirthDate'])->name('account.birth-date.update');
    Route::patch('/account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::post('/account/password-reset-email', [AccountController::class, 'sendPasswordReset'])->middleware('throttle:3,10')->name('account.password.reset-email');
    Route::get('/', HomeController::class)->name('home');
    Route::get('/dashboard', DashboardController::class)->middleware('permission:reports.dashboard')->name('dashboard');
    Route::get('/birthdays', [BirthdayController::class, 'index'])->name('birthdays.index');
    Route::patch('/birthdays/settings', [BirthdayController::class, 'updateSettings'])->name('birthdays.settings.update');

    Route::get('/my-agenda', [CalendarController::class, 'myAgenda'])->middleware('permission:agenda.view')->name('my.agenda');
    Route::get('/my-agenda/assignments/{assignment}/troca', [CalendarController::class, 'swapOptions'])->middleware(['permission:agenda.view','permission:schedule.swap'])->name('my.agenda.swap-options');
    Route::get('/louvores-programados', [CalendarController::class, 'programmedSongs'])->name('programmed-songs.index');
    Route::get('/calendar/sync', [CalendarController::class, 'sync'])->name('calendar.sync');
    Route::get('/my-agenda/services/{service}/louvores', [CalendarController::class, 'publishedRepertoire'])->middleware('permission:agenda.view')->name('my.agenda.repertoire');
    Route::get('/my-agenda/published-schedule.pdf', [CalendarController::class, 'publishedSchedulePdf'])->middleware('permission:published_schedule.view')->name('my.agenda.schedule.pdf');
    Route::post('/my-calendar/regenerate-feed', [CalendarController::class, 'regenerateFeed'])->name('calendar.feed.regenerate');
    Route::post('/my-agenda/google/connect', [GoogleIntegrationController::class, 'connectPersonal'])->middleware('permission:agenda.view')->name('my.agenda.google.connect');
    Route::post('/my-agenda/google/{integration}/disconnect', [GoogleIntegrationController::class, 'disconnectPersonal'])->middleware('permission:agenda.view')->name('my.agenda.google.disconnect');

    Route::get('/availability', [AvailabilityController::class, 'index'])->middleware('permission:availability.manage')->name('availability.index');
    Route::post('/availability/fixed', [AvailabilityController::class, 'storeFixed'])->middleware('permission:availability.manage')->name('availability.fixed.store');
    Route::patch('/availability/fixed/{availability}', [AvailabilityController::class, 'updateFixed'])->middleware('permission:availability.manage')->name('availability.fixed.update');
    Route::delete('/availability/fixed/{availability}', [AvailabilityController::class, 'destroyFixed'])->middleware('permission:availability.manage')->name('availability.fixed.destroy');
    Route::post('/availability/date', [AvailabilityController::class, 'storeDateOverride'])->middleware('permission:availability.manage')->name('availability.date.store');
    Route::delete('/availability/date/{override}', [AvailabilityController::class, 'destroyDateOverride'])->middleware('permission:availability.manage')->name('availability.date.destroy');
    Route::post('/availability/temporary', [AvailabilityController::class, 'storeTemporary'])->middleware('permission:availability.manage')->name('availability.temporary.store');
    Route::delete('/availability/temporary/{unavailability}', [AvailabilityController::class, 'destroyTemporary'])->middleware('permission:availability.manage')->name('availability.temporary.destroy');

    Route::get('/calendar', [CalendarController::class, 'index'])->middleware('permission:calendar.view')->name('calendar.index');
    Route::get('/calendar/services/{service}', [CalendarController::class, 'serviceDetail'])->middleware('permission:calendar.view')->name('calendar.service-detail');
    Route::get('/calendar/events/create', [CalendarEventController::class, 'create'])->middleware('permission:calendar_event.create')->name('calendar.events.create');
    Route::post('/calendar/events', [CalendarEventController::class, 'store'])->middleware('permission:calendar_event.create')->name('calendar.events.store');
    Route::get('/calendar/events/{entry}/edit', [CalendarEventController::class, 'edit'])->middleware('permission:calendar_event.update')->name('calendar.events.edit');
    Route::patch('/calendar/events/{entry}', [CalendarEventController::class, 'update'])->middleware('permission:calendar_event.update')->name('calendar.events.update');
    Route::post('/calendar/events/{entry}/cancel', [CalendarEventController::class, 'cancel'])->middleware('permission:calendar_event.cancel')->name('calendar.events.cancel');
    Route::delete('/calendar/events/{entry}', [CalendarEventController::class, 'destroy'])->middleware('permission:calendar_event.delete')->name('calendar.events.destroy');
    Route::get('/calendar/{entry}/ics', [CalendarController::class, 'exportEvent'])->middleware('permission:calendar.export')->name('calendar.event.ics');

    Route::get('/special-events', [SpecialEventController::class, 'index'])->middleware('permission:special_events.view')->name('special-events.index');
    Route::get('/special-events/create', [SpecialEventController::class, 'create'])->middleware('permission:special_events.manage')->name('special-events.create');
    Route::post('/special-events', [SpecialEventController::class, 'store'])->middleware('permission:special_events.manage')->name('special-events.store');
    Route::get('/special-events/{specialEvent}/edit', [SpecialEventController::class, 'edit'])->middleware('permission:special_events.manage')->name('special-events.edit');
    Route::patch('/special-events/{specialEvent}', [SpecialEventController::class, 'update'])->middleware('permission:special_events.manage')->name('special-events.update');
    Route::delete('/special-events/{specialEvent}', [SpecialEventController::class, 'destroy'])->middleware('permission:special_events.manage')->name('special-events.destroy');
    Route::get('/special-events/{specialEvent}', [SpecialEventController::class, 'show'])->middleware('permission:special_events.view')->name('special-events.show');
    Route::post('/special-events/{specialEvent}/respond', [SpecialEventController::class, 'respond'])->middleware('permission:special_events.respond')->name('special-events.respond');
    Route::post('/special-events/{specialEvent}/minister', [SpecialEventController::class, 'assignMinister'])->middleware('permission:special_events.manage')->name('special-events.assign-minister');
    Route::post('/special-events/{specialEvent}/cancel', [SpecialEventController::class, 'cancel'])->middleware('permission:special_events.manage')->name('special-events.cancel');

    Route::get('/people', [PeopleController::class, 'index'])->middleware('permission:users.view')->name('people.index');
    Route::get('/people/create', [PeopleController::class, 'create'])->middleware('permission:users.create')->name('people.create');
    Route::post('/people', [PeopleController::class, 'store'])->middleware('permission:users.create')->name('people.store');
    Route::get('/people/{person}/edit', [PeopleController::class, 'edit'])->middleware('permission:users.update')->name('people.edit');
    Route::patch('/people/{person}', [PeopleController::class, 'update'])->middleware('permission:users.update')->name('people.update');
    Route::delete('/people/{person}', [PeopleController::class, 'destroy'])->middleware('permission:users.disable')->name('people.destroy');
    Route::get('/congregations', [CongregationController::class, 'index'])->middleware('permission:administration.settings')->name('congregations.index');
    Route::post('/congregations', [CongregationController::class, 'store'])->middleware('permission:administration.settings')->name('congregations.store');
    Route::patch('/congregations/{congregation}', [CongregationController::class, 'update'])->middleware('permission:administration.settings')->name('congregations.update');
    Route::delete('/congregations/{congregation}', [CongregationController::class, 'destroy'])->middleware('permission:administration.settings')->name('congregations.destroy');
    Route::post('/congregations/{congregation}/restore', [CongregationController::class, 'restore'])->middleware('permission:administration.settings')->name('congregations.restore');
    Route::post('/locations', [CongregationController::class, 'storeLocation'])->middleware('permission:administration.settings')->name('locations.store');
    Route::patch('/locations/{location}', [CongregationController::class, 'updateLocation'])->middleware('permission:administration.settings')->name('locations.update');
    Route::delete('/locations/{location}', [CongregationController::class, 'destroyLocation'])->middleware('permission:administration.settings')->name('locations.destroy');
    Route::post('/locations/{location}/restore', [CongregationController::class, 'restoreLocation'])->middleware('permission:administration.settings')->name('locations.restore');
    Route::get('/service-types', [ServiceTypeController::class, 'index'])->middleware('permission:administration.settings')->name('service-types.index');
    Route::post('/service-types', [ServiceTypeController::class, 'store'])->middleware('permission:administration.settings')->name('service-types.store');
    Route::patch('/service-types/{serviceType}', [ServiceTypeController::class, 'update'])->middleware('permission:administration.settings')->name('service-types.update');
    Route::delete('/service-types/{serviceType}', [ServiceTypeController::class, 'destroy'])->middleware('permission:administration.settings')->name('service-types.destroy');
    Route::post('/service-types/{serviceType}/restore', [ServiceTypeController::class, 'restore'])->middleware('permission:administration.settings')->name('service-types.restore');

    Route::get('/services/month-template', [ServiceController::class, 'monthTemplate'])->middleware('permission:services.create')->name('services.month-template');
    Route::post('/services/month-template', [ServiceController::class, 'applyMonthTemplate'])->middleware('permission:services.create')->name('services.month-template.apply');
    Route::get('/services/recurring', [ServiceController::class, 'recurringRules'])->middleware('permission:administration.settings')->name('services.recurring');
    Route::post('/services/recurring', [ServiceController::class, 'storeRecurringRule'])->middleware('permission:administration.settings')->name('services.recurring.store');
    Route::patch('/services/recurring/{rule}', [ServiceController::class, 'updateRecurringRule'])->middleware('permission:administration.settings')->name('services.recurring.update');
    Route::delete('/services/recurring/{rule}', [ServiceController::class, 'destroyRecurringRule'])->middleware('permission:administration.settings')->name('services.recurring.destroy');
    Route::post('/services/recurring/{rule}/restore', [ServiceController::class, 'restoreRecurringRule'])->middleware('permission:administration.settings')->name('services.recurring.restore');
    Route::post('/services/generate-month', [ServiceController::class, 'generateMonth'])->middleware('permission:administration.settings')->name('services.generate-month');
    Route::get('/services', [ServiceController::class, 'index'])->middleware('permission:services.view')->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->middleware('permission:services.create')->name('services.create');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('permission:services.create')->name('services.store');
    Route::post('/services/month-batch', [ServiceController::class, 'storeMonthBatch'])->middleware('permission:services.create')->name('services.month-batch.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->middleware('permission:services.view')->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->middleware('permission:services.update')->name('services.edit');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])->middleware('permission:services.update')->name('services.update');
    Route::post('/services/{service}/cancel', [ServiceController::class, 'cancel'])->middleware('permission:services.cancel')->name('services.cancel');
    Route::post('/services/{service}/reset-test-data', [ServiceController::class, 'resetTestData'])->middleware('permission:services.delete')->name('services.reset-test-data');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->middleware('permission:services.delete')->name('services.destroy');
    Route::post('/services/{service}/restore', [ServiceController::class, 'restore'])->middleware('permission:services.restore')->name('services.restore');

    Route::get('/schedule', [ScheduleController::class, 'index'])->middleware('permission:schedule.view')->name('schedule.index');
    Route::get('/schedule/pdf', [ScheduleController::class, 'pdf'])->middleware('permission:schedule.view')->name('schedule.pdf');
    Route::post('/schedule/generate', [ScheduleController::class, 'generate'])->middleware('permission:schedule.generate')->name('schedule.generate');
    Route::post('/schedule/publish', [ScheduleController::class, 'publish'])->middleware('permission:schedule.publish')->name('schedule.publish');
    Route::post('/schedule/reset', [ScheduleController::class, 'reset'])->middleware('permission:schedule.reset')->name('schedule.reset');
    Route::post('/schedule/manual-assign/{service}', [ScheduleController::class, 'manualAssign'])->middleware('permission:schedule.manual_assign')->name('schedule.manual-assign');
    Route::post('/schedule/services/{service}/additional-ministers', [ScheduleController::class, 'syncAdditionalMinisters'])->middleware('permission:schedule.additional_ministers')->name('schedule.additional-ministers');
    Route::patch('/schedule/{assignment}', [ScheduleController::class, 'update'])->middleware('permission:schedule.update')->name('schedule.update');
    Route::post('/schedule/{assignment}/confirm', [ScheduleController::class, 'confirm'])->name('schedule.confirm');
    Route::post('/schedule/{assignment}/unavailable', [ScheduleController::class, 'unavailable'])->name('schedule.unavailable');

    Route::get('/swaps', [SwapController::class, 'index'])->middleware('permission:schedule.swap')->name('swaps.index');
    Route::post('/swaps', [SwapController::class, 'store'])->middleware('permission:schedule.swap')->name('swaps.store');
    Route::post('/swaps/{swap}/target', [SwapController::class, 'setTarget'])->middleware('permission:schedule.force_swap')->name('swaps.target');
    Route::post('/swaps/{swap}/accept', [SwapController::class, 'accept'])->name('swaps.accept');
    Route::post('/swaps/{swap}/reject', [SwapController::class, 'reject'])->name('swaps.reject');
    Route::post('/swaps/{swap}/approve', [SwapController::class, 'approve'])->middleware('permission:schedule.force_swap')->name('swaps.approve');
    Route::post('/swaps/assignments/{assignment}/replace', [SwapController::class, 'forceReplace'])->middleware('permission:schedule.force_swap')->name('swaps.force-replace');

    Route::get('/songs', [SongController::class, 'index'])->middleware('permission:songs.view')->name('songs.index');
    Route::get('/song-history', [SongHistoryController::class, 'index'])->middleware('permission:song_history.view')->name('songs.history');
    Route::get('/songs/import', [SongController::class, 'importForm'])->middleware('permission:songs.create')->name('songs.import');
    Route::post('/songs/import/catalog', [SongController::class, 'importBuiltIn'])->middleware('permission:songs.create')->name('songs.import.catalog');
    Route::post('/songs/import/csv', [SongController::class, 'importCsv'])->middleware('permission:songs.create')->name('songs.import.csv');
    Route::get('/songs/import/template.csv', [SongController::class, 'importTemplate'])->middleware('permission:songs.create')->name('songs.import.template');
    Route::get('/songs/create', [SongController::class, 'create'])->middleware('permission:songs.create')->name('songs.create');
    Route::post('/songs', [SongController::class, 'store'])->middleware('permission:songs.create')->name('songs.store');
    Route::get('/songs/{song}', [SongController::class, 'show'])->middleware('permission:songs.view')->name('songs.show');
    Route::get('/songs/{song}/edit', [SongController::class, 'edit'])->middleware('permission:songs.update')->name('songs.edit');
    Route::patch('/songs/{song}', [SongController::class, 'update'])->middleware('permission:songs.update')->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->middleware('permission:songs.archive')->name('songs.destroy');
    Route::post('/songs/{song}/archive', [SongController::class, 'archive'])->middleware('permission:songs.archive')->name('songs.archive');
    Route::post('/songs/{song}/restore', [SongController::class, 'restore'])->middleware('permission:songs.archive')->name('songs.restore');
    Route::post('/songs/{song}/versions', [SongController::class, 'storeVersion'])->middleware('permission:songs.update')->name('songs.versions.store');
    Route::post('/songs/{song}/versions/{version}/default', [SongController::class, 'setDefaultVersion'])->middleware('permission:songs.update')->name('songs.versions.default');
    Route::post('/songs/{song}/versions/{version}/archive', [SongController::class, 'archiveVersion'])->middleware('permission:songs.update')->name('songs.versions.archive');
    Route::post('/song-requests', [SongController::class, 'requestSong'])->middleware('permission:songs.request')->name('song-requests.store');
    Route::post('/song-requests/{songRequest}/approve', [SongController::class, 'approveRequest'])->middleware('permission:songs.approve')->name('song-requests.approve');
    Route::post('/song-requests/{songRequest}/reject', [SongController::class, 'rejectRequest'])->middleware('permission:songs.approve')->name('song-requests.reject');

    Route::get('/services/{service}/repertoire', [RepertoireController::class, 'show'])->middleware('permission:repertoire.view')->name('repertoire.show');

    Route::get('/rehearsals', [RehearsalController::class, 'index'])->middleware('permission:rehearsals.view')->name('rehearsals.index');
    Route::post('/rehearsals', [RehearsalController::class, 'store'])->middleware('permission:rehearsals.manage')->name('rehearsals.store');
    Route::patch('/rehearsals/{item}', [RehearsalController::class, 'update'])->middleware('permission:rehearsals.manage')->name('rehearsals.update');
    Route::delete('/rehearsals/{item}', [RehearsalController::class, 'remove'])->middleware('permission:rehearsals.manage')->name('rehearsals.remove');
    Route::post('/rehearsals/{item}/reactivate', [RehearsalController::class, 'reactivate'])->middleware('permission:rehearsals.manage')->name('rehearsals.reactivate');
    Route::patch('/rehearsals/repertoire-items/{item}/key', [RehearsalController::class, 'updateRepertoireKey'])->middleware('permission:rehearsals.view')->name('rehearsals.repertoire-key.update');
    Route::patch('/rehearsals/repertoires/{repertoire}/opening-key', [RehearsalController::class, 'updateOpeningKey'])->middleware('permission:rehearsals.view')->name('rehearsals.opening-key.update');

    // RC27: Modo Culto usa autorização contextual por culto. O ministro só opera
    // a própria escala publicada; administração pode operar qualquer culto.
    Route::get('/services/{service}/execution', [ExecutionController::class, 'show'])->name('execution.show');
    Route::post('/services/{service}/execution/start', [ExecutionController::class, 'start'])->name('execution.start');
    Route::post('/executions/{execution}/repertoire/{item}', [ExecutionController::class, 'performRepertoire'])->name('execution.perform-repertoire');
    Route::post('/executions/{execution}/song', [ExecutionController::class, 'performSong'])->name('execution.perform-song');
    Route::patch('/executions/{execution}/songs/{songExecution}', [ExecutionController::class, 'updatePerformedSong'])->name('execution.song.update');
    Route::delete('/executions/{execution}/songs/{songExecution}', [ExecutionController::class, 'removePerformedSong'])->name('execution.song.destroy');
    Route::post('/executions/{execution}/participant', [ExecutionController::class, 'addParticipant'])->middleware('permission:participation.manage_roster')->name('execution.participant');
    Route::post('/executions/{execution}/minister', [ExecutionController::class, 'addMinister'])->middleware('permission:participation.manage_roster')->name('execution.minister');
    Route::delete('/executions/{execution}/participant/{participant}', [ExecutionController::class, 'removeParticipant'])->middleware('permission:participation.manage_roster')->name('execution.participant.destroy');
    Route::delete('/executions/{execution}/minister/{minister}', [ExecutionController::class, 'removeMinister'])->middleware('permission:participation.manage_roster')->name('execution.minister.destroy');
    Route::post('/executions/{execution}/finish', [ExecutionController::class, 'finish'])->name('execution.finish');
    Route::post('/executions/{execution}/cancel', [ExecutionController::class, 'cancel'])->name('execution.cancel');

    Route::get('/actions', [ActionCenterController::class, 'index'])->name('actions.index');
    Route::get('/actions/{item}/open', [ActionCenterController::class, 'open'])->name('actions.open');
    Route::post('/actions/{item}/complete', [ActionCenterController::class, 'complete'])->name('actions.complete');
    Route::post('/actions/{item}/dismiss', [ActionCenterController::class, 'dismiss'])->name('actions.dismiss');

    Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');
    Route::get('/admin/backups', [DatabaseBackupController::class, 'index'])->middleware('administrator')->name('admin.backups.index');
    Route::get('/admin/whatsapp', [WhatsappSettingsController::class, 'index'])->middleware(['administrator','permission:administration.whatsapp'])->name('admin.whatsapp.index');
    Route::patch('/admin/whatsapp', [WhatsappSettingsController::class, 'update'])->middleware(['administrator','permission:administration.whatsapp'])->name('admin.whatsapp.update');
    Route::post('/admin/whatsapp/check', [WhatsappSettingsController::class, 'check'])->middleware(['administrator','permission:administration.whatsapp','throttle:6,5'])->name('admin.whatsapp.check');
    Route::post('/admin/whatsapp/test', [WhatsappSettingsController::class, 'test'])->middleware(['administrator','permission:administration.whatsapp','throttle:3,5'])->name('admin.whatsapp.test');
    Route::post('/admin/backups', [DatabaseBackupController::class, 'store'])->middleware(['administrator', 'throttle:2,10'])->name('admin.backups.store');
    Route::post('/admin/backups/import', [DatabaseBackupController::class, 'import'])->middleware(['administrator', 'throttle:2,10'])->name('admin.backups.import');
    Route::post('/admin/backups/{filename}/restore', [DatabaseBackupController::class, 'restore'])->middleware(['administrator', 'throttle:1,10'])->where('filename', 'sgml-db-[0-9_\-a-f]+\.dump')->name('admin.backups.restore');
    Route::get('/admin/backups/{filename}', [DatabaseBackupController::class, 'download'])->middleware('administrator')->where('filename', 'sgml-db-[0-9_\-a-f]+\.dump')->name('admin.backups.download');
    Route::delete('/admin/backups/{filename}', [DatabaseBackupController::class, 'destroy'])->middleware('administrator')->where('filename', 'sgml-db-[0-9_\-a-f]+\.dump')->name('admin.backups.destroy');
    Route::post('/admin/assistance/start', [AdminAssistanceController::class, 'start'])->name('admin.assistance.start');
    Route::post('/admin/assistance/stop', [AdminAssistanceController::class, 'stop'])->name('admin.assistance.stop');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.dashboard')->name('reports.index');
    Route::get('/reports/songs/{song}', [ReportController::class, 'song'])->middleware('permission:reports.dashboard')->name('reports.song');
    Route::get('/audit', [AuditController::class, 'index'])->middleware(['administrator', 'permission:administration.audit'])->name('audit.index');

    Route::get('/admin/catalogs', [AdminCatalogController::class, 'index'])->middleware('permission:administration.settings')->name('admin.catalogs');
    Route::post('/admin/catalogs/ministry-roles', [AdminCatalogController::class, 'storeMinistryRole'])->middleware('permission:administration.settings')->name('admin.ministry-roles.store');
    Route::patch('/admin/catalogs/ministry-roles/{role}', [AdminCatalogController::class, 'updateMinistryRole'])->middleware('permission:administration.settings')->name('admin.ministry-roles.update');
    Route::delete('/admin/catalogs/ministry-roles/{role}', [AdminCatalogController::class, 'destroyMinistryRole'])->middleware('permission:administration.settings')->name('admin.ministry-roles.destroy');
    Route::post('/admin/catalogs/ministry-roles/{role}/restore', [AdminCatalogController::class, 'restoreMinistryRole'])->middleware('permission:administration.settings')->name('admin.ministry-roles.restore');
    Route::post('/admin/catalogs/song-categories', [AdminCatalogController::class, 'storeSongCategory'])->middleware('permission:administration.settings')->name('admin.song-categories.store');
    Route::patch('/admin/catalogs/song-categories/{category}', [AdminCatalogController::class, 'updateSongCategory'])->middleware('permission:administration.settings')->name('admin.song-categories.update');
    Route::delete('/admin/catalogs/song-categories/{category}', [AdminCatalogController::class, 'destroySongCategory'])->middleware('permission:administration.settings')->name('admin.song-categories.destroy');
    Route::post('/admin/catalogs/song-categories/{category}/restore', [AdminCatalogController::class, 'restoreSongCategory'])->middleware('permission:administration.settings')->name('admin.song-categories.restore');
    Route::get('/admin/users', [AdminUserController::class, 'index'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.users');
    Route::post('/admin/users', [AdminUserController::class, 'store'])->middleware(['administrator', 'permission:users.create'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [AdminUserController::class, 'update'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.users.update');
    Route::post('/admin/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.users.password.reset');
    Route::post('/admin/roles', [AdminUserController::class, 'storeRole'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.roles.store');
    Route::patch('/admin/roles/{role}', [AdminUserController::class, 'updateRole'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.roles.update');
    Route::get('/admin/groups', [AdminUserController::class, 'groups'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.groups');
    Route::post('/admin/groups/{role}/members', [AdminUserController::class, 'addRoleMember'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.groups.members.store');
    Route::delete('/admin/groups/{role}/members/{user}', [AdminUserController::class, 'removeRoleMember'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.groups.members.destroy');
    Route::get('/admin/minister-eligibility', [AdminMinisterEligibilityController::class, 'index'])->middleware('permission:administration.settings')->name('admin.minister-eligibility');
    Route::post('/admin/minister-eligibility/{serviceType}/ministers', [AdminMinisterEligibilityController::class, 'store'])->middleware('permission:administration.settings')->name('admin.minister-eligibility.store');
    Route::delete('/admin/minister-eligibility/{serviceType}/ministers/{person}', [AdminMinisterEligibilityController::class, 'destroy'])->middleware('permission:administration.settings')->name('admin.minister-eligibility.destroy');
    Route::post('/admin/users/{user}/permission-overrides', [AdminUserController::class, 'setOverride'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.users.overrides.store');
    Route::delete('/admin/users/{user}/permission-overrides/{override}', [AdminUserController::class, 'deleteOverride'])->middleware(['administrator', 'permission:users.permissions'])->name('admin.users.overrides.destroy');


    Route::get('/admin/mail', [MailSettingsController::class, 'index'])->middleware(['administrator', 'permission:administration.mail'])->name('admin.mail.index');
    Route::patch('/admin/mail', [MailSettingsController::class, 'update'])->middleware(['administrator', 'permission:administration.mail'])->name('admin.mail.update');
    Route::post('/admin/mail/test', [MailSettingsController::class, 'test'])->middleware(['administrator', 'permission:administration.mail', 'throttle:5,1'])->name('admin.mail.test');


    Route::get('/integrations/google', [GoogleIntegrationController::class, 'index'])->middleware('permission:administration.integrations')->name('integrations.index');
    Route::patch('/integrations/google/settings', [GoogleIntegrationController::class, 'updateSettings'])->middleware('permission:administration.integrations')->name('integrations.google.settings.update');
    Route::post('/integrations/google/connect', [GoogleIntegrationController::class, 'connect'])->middleware('permission:administration.integrations')->name('integrations.google.connect');
    Route::get('/integrations/google/callback', [GoogleIntegrationController::class, 'callback'])->name('integrations.google.callback');
    Route::post('/integrations/google/{integration}/disconnect', [GoogleIntegrationController::class, 'disconnect'])->middleware('permission:administration.integrations')->name('integrations.google.disconnect');
});
