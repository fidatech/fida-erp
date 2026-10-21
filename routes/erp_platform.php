<?php
use App\Http\Controllers\ERP\PlatformController;
use App\Http\Controllers\ERP\PlatformAuthController;
use App\Http\Middleware\ErpPlatformAdmin;
use Illuminate\Support\Facades\Route;
Route::middleware('web')->prefix('platform/erp')->name('erp.platform.')->group(function(){
 Route::get('login',[PlatformAuthController::class,'show'])->name('login');
 Route::post('login',[PlatformAuthController::class,'login'])->middleware('throttle:6,1')->name('login.submit');
});
Route::middleware(['web','auth:web',ErpPlatformAdmin::class])->prefix('platform/erp')->name('erp.platform.')->group(function(){
 Route::get('/',[PlatformController::class,'index'])->name('index');
 Route::post('logout',[PlatformAuthController::class,'logout'])->name('logout');
 Route::post('plans',[PlatformController::class,'plan'])->name('plans');
 Route::post('resellers',[PlatformController::class,'reseller'])->name('resellers');
 Route::post('subscriptions',[PlatformController::class,'subscribe'])->name('subscriptions');
 Route::post('invoices',[PlatformController::class,'invoice'])->name('invoices');
 Route::post('payments',[PlatformController::class,'payment'])->name('payments');
 Route::post('tenants/{id}/status',[PlatformController::class,'suspend'])->whereNumber('id')->name('tenant.status');
});
