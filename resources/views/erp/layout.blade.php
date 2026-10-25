{{-- Use the ORIGINAL Fida Admin Master v2 component if the customer installed it; keep an operational fallback for development. --}}
@if(view()->exists('components.admin.layout'))
@include('erp._admin-layout')
@else
@include('erp._fallback-layout')
@endif
