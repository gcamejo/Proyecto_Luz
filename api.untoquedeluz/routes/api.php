<?php

use App\Http\Controllers\Api\ActividadController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\BookingAdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\LegalConsentController;
use App\Http\Controllers\Api\PaymentAdminController;
use App\Http\Controllers\Api\PaymentStudentController;
use App\Http\Controllers\Api\PublicYogaCyclesController;
use App\Http\Controllers\Api\YoguiniController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/public/legal-parameters', [LegalConsentController::class, 'publicParameters'])->middleware('throttle:public');

Route::middleware(['auth:sanctum', 'legal-consent'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/legal-consents/status', [LegalConsentController::class, 'status']);
    Route::post('/legal-consents/accept', [LegalConsentController::class, 'accept']);
    Route::get('/profile/export', [YoguiniController::class, 'exportProfile']);
    Route::post('/profile/deletion-request', [YoguiniController::class, 'requestDeletion']);
});

Route::post('/login', [YoguiniController::class, 'login'])->middleware('throttle:login');
Route::post('/yoguinis', [YoguiniController::class, 'store'])->middleware('throttle:registration');
Route::get('/public/yoga/cycles', [PublicYogaCyclesController::class, 'index'])->middleware('throttle:public-yoga-cycles');

Route::get('/actividades', [ActividadController::class, 'index'])->middleware('throttle:public');

Route::middleware(['auth:sanctum', 'legal-consent', 'admin'])->prefix('admin/notifications')->group(function () {
    Route::get('/', [AdminNotificationController::class, 'index']);
    Route::post('/{notificationId}/read', [AdminNotificationController::class, 'markRead']);
    Route::delete('/{notificationId}', [AdminNotificationController::class, 'destroy']);
});



//Rutas protegidas
Route::middleware(['auth:sanctum', 'legal-consent'])->group(function () {
    Route::get('/profile', [YoguiniController::class, 'profile']);
    Route::patch('/profile', [YoguiniController::class, 'updateProfile'])->middleware('throttle:password-change');
    Route::get('/perfil', [YoguiniController::class, 'profile']);
    Route::patch('/perfil', [YoguiniController::class, 'updateProfile'])->middleware('throttle:password-change');
    
    //Rutas a tabla yoguinis
    
    Route::middleware('admin')->controller(YoguiniController::class)->group(function (){
        Route::post('/admin/yoguinis', 'storeByAdmin');
        Route::get('/yoguinis', 'index');
        Route::get('/yoguinis/{id}', 'edit');
        Route::patch('/yoguinis/{id}',  'update');
        Route::delete('/yoguinis/{id}', 'destroy');
    });

    Route::get('/admin/yoguinis/{yoguini}/legal-consents', [LegalConsentController::class, 'forStudent'])
        ->middleware('can:viewAny,App\\Models\\Consentimiento');
    
    //Rutas a tabla actividades
    
    Route::controller(ActividadController::class)->group(function (){
        Route::post('/actividadesImg', 'storeFile');
        Route::post('/actividades', 'create');
        Route::get('/actividades/{id}', 'edit' );
        Route::patch('/actividades/{id}',  'update' );
        Route::delete('/actividades/{id}', 'destroy' );
    });



});

Route::middleware(['auth:sanctum', 'legal-consent'])->prefix('payments')->group(function () {
    Route::get('/me', [PaymentStudentController::class, 'show']);

    Route::prefix('admin')->middleware('can:manage,App\\Models\\Cargo')->group(function () {
        Route::get('/charges', [PaymentAdminController::class, 'charges']);
        Route::post('/charges', [PaymentAdminController::class, 'storeCharge']);
        Route::patch('/charges/{cargo}/discount', [PaymentAdminController::class, 'discount']);
        Route::patch('/charges/{cargo}/void', [PaymentAdminController::class, 'voidCharge']);
        Route::post('/charges/{cargo}/apply-credit', [PaymentAdminController::class, 'applyCredit']);
        Route::post('/cycles/{ciclo}/charges/generate', [PaymentAdminController::class, 'generateCharges']);
        Route::get('/summary', [PaymentAdminController::class, 'summary']);
        Route::get('/students/{yoguini}', [PaymentAdminController::class, 'student']);
        Route::post('/payments', [PaymentAdminController::class, 'registerPayment']);
        Route::patch('/payments/{pago}/void', [PaymentAdminController::class, 'voidPayment']);
        Route::patch('/applications/{aplicacion}/void', [PaymentAdminController::class, 'voidApplication']);
    });
});

Route::middleware(['auth:sanctum', 'legal-consent'])->prefix('booking')->group(function () {
    Route::get('/cycles/active', [BookingController::class, 'activeCycles']);
    Route::get('/me', [BookingController::class, 'myBookings']);
    Route::get('/credits', [BookingController::class, 'myCredits']);
    Route::post('/cycles/{ciclo}/enroll', [BookingController::class, 'enroll']);
    Route::post('/enrollments/{inscripcion}/cancel', [BookingController::class, 'cancelEnrollment']);
    Route::post('/reservations/{reserva}/cancel', [BookingController::class, 'cancelReservation']);
    Route::get('/recoveries/available-classes', [BookingController::class, 'recoveryClasses']);
    Route::post('/recoveries/{recuperacion}/book', [BookingController::class, 'bookRecovery']);

    Route::prefix('admin')->middleware('can:manage,App\\Models\\Clase')->group(function () {
        Route::get('/schedules', [BookingAdminController::class, 'schedules']);
        Route::post('/schedules', [BookingAdminController::class, 'createSchedule']);
        Route::get('/schedules/{horario}', [BookingAdminController::class, 'showSchedule']);
        Route::patch('/schedules/{horario}', [BookingAdminController::class, 'updateSchedule']);
        Route::delete('/schedules/{horario}', [BookingAdminController::class, 'deleteSchedule']);

        Route::get('/cycles', [BookingAdminController::class, 'cycles']);
        Route::post('/cycles', [BookingAdminController::class, 'createCycle']);
        Route::get('/cycles/{ciclo}', [BookingAdminController::class, 'showCycle']);
        Route::patch('/cycles/{ciclo}', [BookingAdminController::class, 'updateCycle']);
        Route::delete('/cycles/{ciclo}', [BookingAdminController::class, 'deleteCycle']);
        Route::post('/cycles/{ciclo}/classes/generate', [BookingAdminController::class, 'generateClasses']);

        Route::get('/classes', [BookingAdminController::class, 'classes']);
        Route::get('/classes/{clase}/reservations', [BookingAdminController::class, 'classReservations']);
        Route::patch('/classes/{clase}/cancel', [BookingAdminController::class, 'cancelClass']);
        Route::post('/classes/{clase}/cancel', [BookingAdminController::class, 'cancelClass']);
        Route::post('/classes/{clase}/complete', [BookingAdminController::class, 'completeClass']);
        Route::patch('/reservations/{reserva}/attendance', [BookingAdminController::class, 'markAttendance']);
        Route::get('/holidays', [BookingAdminController::class, 'holidays']);
        Route::post('/holidays', [BookingAdminController::class, 'createHoliday']);
        Route::patch('/holidays/{feriado}', [BookingAdminController::class, 'updateHoliday']);
        Route::delete('/holidays/{feriado}', [BookingAdminController::class, 'deleteHoliday']);
    });
});

/*
Route::controller(YoguiniController::class)->group(function () {
    Route::get('/yoguinis', 'index');
    Route::post('/yoguinis', 'store');
    Route::get('/yoguinis/{id}', 'edit');
    Route::patch('/yoguinis/{id}', 'update');
    Route::delete('/yoguinis/{id}', 'destroy');
});
*/