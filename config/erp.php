<?php
return [
    'central_connection' => env('ERP_CENTRAL_CONNECTION', 'mysql'),
    'tenant_connection' => 'erp_tenant',
    'base_domain' => env('ERP_BASE_DOMAIN', 'erp.localhost'),
    'platform_host' => env('ERP_PLATFORM_HOST', 'erp.localhost'),
    'primary_color' => '#142b45',
    'accent_color' => '#e98a28',
    'page_size' => 25,
];
