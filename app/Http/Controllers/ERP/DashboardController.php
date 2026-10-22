<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\BusinessMetricsService;
final class DashboardController extends Controller
{
    public function __invoke(BusinessMetricsService $service)
    {
        return view('erp.dashboard',['metrics'=>$service->snapshot()]);
    }
}