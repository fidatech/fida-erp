<?php

namespace Tests\Feature\ERP;

use Tests\TestCase;

final class ResponsiveUiContractTest extends TestCase
{
    public function test_mobile_list_views_have_labels_and_empty_states(): void
    {
        foreach ([
            'sales/index.blade.php',
            'stock/index.blade.php',
            'customers/index.blade.php',
            'products/index.blade.php',
            'dashboard.blade.php',
        ] as $view) {
            $source = file_get_contents(resource_path('views/erp/'.$view));
            $this->assertIsString($source, $view);
            $this->assertStringContainsString('erp-responsive-list', $source, $view);
            $this->assertStringContainsString('data-label=', $source, $view);
            $this->assertStringContainsString('erp-empty-state', $source, $view);
        }
    }

    public function test_mobile_invoice_and_receipt_editors_expose_field_labels(): void
    {
        foreach (['sales/create.blade.php', 'stock/index.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/erp/'.$view));
            $this->assertStringContainsString('dataset.label', $source, $view);
        }
    }

    public function test_mobile_navigation_has_accessibility_controls(): void
    {
        $source = file_get_contents(resource_path('views/erp/_fallback-layout.blade.php'));
        foreach (['aria-controls="erp-sidebar"', 'aria-expanded="false"', 'erp-menu-overlay', "e.key==='Escape'", 'aria-hidden'] as $marker) {
            $this->assertStringContainsString($marker, $source);
        }
    }

    public function test_responsive_styles_include_phone_tablet_and_mobile_cards(): void
    {
        $css = file_get_contents(public_path('fida-erp/erp.css'));
        foreach (['max-width:600px', 'max-width:820px', 'erp-responsive-list', 'erp-admin-nav', 'erp-mobile-overlay'] as $marker) {
            $this->assertStringContainsString($marker, $css);
        }
    }
}
