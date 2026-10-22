<?php
namespace App\Http\Controllers\ERP;
use App\Http\Controllers\Controller;
use App\Services\ERP\BusinessMetricsService;
use Illuminate\Http\Request;

final class InsightsController extends Controller
{
    public function kpi(BusinessMetricsService $service)
    {
        return view('erp.kpi.index',['metrics'=>$service->snapshot()]);
    }
    public function analytics(BusinessMetricsService $service)
    {
        return view('erp.analytics.index',['metrics'=>$service->snapshot()]);
    }
}
