<?php

use App\Http\Controllers\Api\ActividadController;
use App\Http\Controllers\Api\BookingAdminController;
use App\Http\Controllers\Api\BookingController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login', [YoguiniController::class , 'login']);
Route::post('/yoguinis',[YoguiniController::class , 'store'] );

Route::get('/actividades',[ActividadController::class,'index']);



//Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    
    //Rutas a tabla yoguinis
    
    Route::middleware('admin')->controller(YoguiniController::class)->group(function (){
        Route::get('/yoguinis', 'index');
        Route::get('/yoguinis/{id}', 'edit');
        Route::patch('/yoguinis/{id}',  'update');
        Route::delete('/yoguinis/{id}', 'destroy');
    });
    
    //Rutas a tabla actividades
    
    Route::controller(ActividadController::class)->group(function (){
        Route::post('/actividadesImg', 'storeFile');
        Route::post('/actividades', 'create');
        Route::get('/actividades/{id}', 'edit' );
        Route::patch('/actividades/{id}',  'update' );
        Route::delete('/actividades/{id}', 'destroy' );
    });



});

Route::middleware('auth:sanctum')->prefix('booking')->group(function () {
    Route::get('/cycles/active', [BookingController::class, 'activeCycles']);
    Route::get('/me', [BookingController::class, 'myBookings']);
    Route::post('/cycles/{ciclo}/enroll', [BookingController::class, 'enroll']);
    Route::post('/reservations/{reserva}/cancel', [BookingController::class, 'cancelReservation']);
    Route::get('/recoveries/available-classes', [BookingController::class, 'recoveryClasses']);
    Route::post('/recoveries/{recuperacion}/book', [BookingController::class, 'bookRecovery']);

    Route::prefix('admin')->middleware('admin')->group(function () {
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
        Route::post('/classes/{clase}/cancel', [BookingAdminController::class, 'cancelClass']);
        Route::post('/classes/{clase}/complete', [BookingAdminController::class, 'completeClass']);
        Route::patch('/reservations/{reserva}/attendance', [BookingAdminController::class, 'markAttendance']);
        Route::get('/holidays', [BookingAdminController::class, 'holidays']);
        Route::post('/holidays', [BookingAdminController::class, 'createHoliday']);
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