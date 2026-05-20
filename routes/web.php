<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\StudentController;

use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Auth::check() 
        ? redirect()->route('dashboard') 
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
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

        Route::get('events/{event}/rfid', [AttendanceController::class, 'rfidScanner'])->name('attendance.rfid');
        Route::post('events/{event}/rfid', [AttendanceController::class, 'logRfid'])->name('attendance.logRfid');
        
        Route::get('events/{event}/verify', [AttendanceController::class, 'verifySelfies'])->name('attendance.verify');
        Route::post('attendance/{attendance}/approve/{status}', [AttendanceController::class, 'approveAttendance'])->name('attendance.approve');

        Route::get('events/{event}/survey/create', [SurveyController::class, 'create'])->name('surveys.create');
        Route::post('events/{event}/survey', [SurveyController::class, 'store'])->name('surveys.store');

        Route::get('fines', [FineController::class, 'index'])->name('fines.index');
        Route::post('fines/{fine}/pay', [FineController::class, 'markAsPaid'])->name('fines.markAsPaid');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('admin/departments', [AdminController::class, 'departments'])->name('admin.departments');
        Route::post('admin/departments', [AdminController::class, 'storeDepartment']);
        Route::get('admin/officers', [AdminController::class, 'officers'])->name('admin.officers');
        Route::post('admin/officers', [AdminController::class, 'storeOfficer']);
    });

    Route::middleware('role:student')->group(function () {
        Route::get('my-events', [AttendanceController::class, 'studentEvents'])->name('student.events');
        Route::get('events/{event}/upload', [AttendanceController::class, 'uploadSelfieForm'])->name('attendance.upload');
        Route::post('events/{event}/upload', [AttendanceController::class, 'storeSelfie']);

        // Corrected duplicates
        Route::get('surveys/{survey}', [SurveyController::class, 'show'])->name('surveys.show');
        Route::post('surveys/{survey}', [SurveyController::class, 'submit']);
    });
});
