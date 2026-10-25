<?php
use App\Http\Controllers\ERP\AuthController;
use App\Http\Controllers\ERP\CustomerController;
use App\Http\Controllers\ERP\DashboardController;
use App\Http\Controllers\ERP\ProductController;
use App\Http\Controllers\ERP\SalesController;
use App\Http\Controllers\ERP\StockController;
use App\Http\Controllers\ERP\InvoicePdfController;
use App\Http\Controllers\ERP\LocaleController;
use App\Http\Controllers\ERP\ProfileController;
use App\Http\Middleware\ErpLocale;
use App\Http\Middleware\ErpPermission;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','erp.tenant'])->prefix('erp')->name('erp.')->group(function(){
    Route::get('login',[AuthController::class,'show'])->name('login');
    Route::post('login',[AuthController::class,'login'])->middleware('throttle:erp-tenant-login')->name('login.submit');
    Route::middleware(['auth:erp_tenant',ErpLocale::class])->group(function(){
        Route::post('logout',[AuthController::class,'logout'])->name('logout');
        Route::get('/',DashboardController::class)->middleware(ErpPermission::class.':dashboard.view')->name('dashboard');
        Route::post('locale',LocaleController::class)->name('locale');
        Route::get('profile',[ProfileController::class,'index'])->name('profile');
        Route::put('profile',[ProfileController::class,'save'])->name('profile.save');
        Route::put('password',[ProfileController::class,'password'])->name('password.change');
        Route::get('customers',[CustomerController::class,'index'])->middleware(ErpPermission::class.':customers.manage')->name('customers.index');
        Route::get('customers/data',[CustomerController::class,'data'])->middleware(ErpPermission::class.':customers.manage')->name('customers.data');
        Route::get('customers/options',[CustomerController::class,'select2'])->middleware(ErpPermission::class.':customers.manage')->name('customers.select2');
        Route::post('customers',[CustomerController::class,'store'])->middleware(ErpPermission::class.':customers.manage')->name('customers.store');
        Route::get('products',[ProductController::class,'index'])->middleware(ErpPermission::class.':products.manage')->name('products.index');
        Route::get('products/data',[ProductController::class,'data'])->middleware(ErpPermission::class.':products.manage')->name('products.data');
        Route::get('products/options',[ProductController::class,'select2'])->middleware(ErpPermission::class.':products.manage')->name('products.select2');
        Route::post('products',[ProductController::class,'store'])->middleware(ErpPermission::class.':products.manage')->name('products.store');
        Route::get('stock',[StockController::class,'index'])->middleware(ErpPermission::class.':stock.manage')->name('stock.index');
        Route::post('stock/suppliers',[StockController::class,'addSupplier'])->middleware(ErpPermission::class.':stock.manage')->name('stock.suppliers');
        Route::post('stock/receive',[StockController::class,'receive'])->middleware(ErpPermission::class.':stock.manage')->name('stock.receive');
        Route::get('sales',[SalesController::class,'index'])->middleware(ErpPermission::class.':sales.manage')->name('sales.index');
        Route::get('sales/create',[SalesController::class,'create'])->middleware(ErpPermission::class.':sales.manage')->name('sales.create');
        Route::post('sales',[SalesController::class,'store'])->middleware(ErpPermission::class.':sales.manage')->name('sales.store');
        Route::get('sales/{id}/pdf',InvoicePdfController::class)->whereNumber('id')->middleware(ErpPermission::class.':sales.manage')->name('sales.pdf');
        Route::post('sales/{id}/post',[SalesController::class,'post'])->whereNumber('id')->middleware(ErpPermission::class.':sales.post')->name('sales.post');
    });
});
