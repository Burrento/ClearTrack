<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ClearanceController;
use App\Http\Controllers\BackupController;

use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Auth::check() 
        ? redirect()->route('dashboard') 
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('settings', [AuthController::class, 'showSettings'])->name('settings');
    Route::post('settings/password', [AuthController::class, 'updatePassword'])->name('settings.password');

    Route::middleware('role:officer')->group(function () {
        Route::resource('events', EventController::class);
        
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::post('students', [StudentController::class, 'store'])->name('students.store');
        Route::get('students/import/template', [StudentController::class, 'importTemplate'])->name('students.importTemplate');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import');
        Route::get('students/{user}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('students/{user}', [StudentController::class, 'update'])->name('students.update');
        Route::post('students/{user}/toggle-active', [StudentController::class, 'toggleActive'])->name('students.toggleActive');

        Route::get('events/{event}/rfid', [AttendanceController::class, 'rfidScanner'])->name('attendance.rfid');
        Route::post('events/{event}/rfid', [AttendanceController::class, 'logRfid'])->name('attendance.logRfid');
        

        Route::get('events/{event}/survey/create', [SurveyController::class, 'create'])->name('surveys.create');
        Route::post('events/{event}/survey', [SurveyController::class, 'store'])->name('surveys.store');

        Route::get('fines', [FineController::class, 'index'])->name('fines.index');
        Route::post('fines/{fine}/pay', [FineController::class, 'markAsPaid'])->name('fines.markAsPaid');

        Route::post('events/{event}/process-fines', [EventController::class, 'processFines'])->name('events.processFines');
        Route::post('events/{event}/sessions/{session}/start', [EventController::class, 'startSession'])->name('events.sessions.start');
        Route::post('events/{event}/sessions/{session}/end', [EventController::class, 'endSession'])->name('events.sessions.end');
        Route::post('events/{event}/end', [EventController::class, 'endEvent'])->name('events.end');

        // Clearance is automatic (no unpaid fines); this lists who is cleared and who still owes
        Route::get('clearance', [ClearanceController::class, 'index'])->name('clearance.index');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('admin/departments', [AdminController::class, 'departments'])->name('admin.departments');
        Route::post('admin/departments', [AdminController::class, 'storeDepartment']);
        Route::get('admin/departments/{department}/edit', [AdminController::class, 'editDepartment'])->name('admin.departments.edit');
        Route::put('admin/departments/{department}', [AdminController::class, 'updateDepartment'])->name('admin.departments.update');
        Route::post('admin/departments/{department}/archive', [AdminController::class, 'toggleArchive'])->name('admin.departments.toggleArchive');
        Route::get('admin/officers', [AdminController::class, 'officers'])->name('admin.officers');
        Route::post('admin/officers', [AdminController::class, 'storeOfficer']);
        Route::get('admin/semesters', [AdminController::class, 'semesters'])->name('admin.semesters');
        Route::post('admin/semesters', [AdminController::class, 'startSemester']);
        Route::get('admin/logs', [AdminController::class, 'logs'])->name('admin.logs');
        Route::get('admin/backups', [BackupController::class, 'index'])->name('admin.backups');
        Route::post('admin/backups', [BackupController::class, 'store'])->name('admin.backups.store');
        Route::get('admin/backups/{file}', [BackupController::class, 'download'])->name('admin.backups.download');
    });

    Route::middleware('role:student')->group(function () {
        Route::get('my-events', [AttendanceController::class, 'studentEvents'])->name('student.events');

        // Corrected duplicates
        Route::get('surveys/{survey}', [SurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}', [SurveyController::class, 'submit']);
    });
});
