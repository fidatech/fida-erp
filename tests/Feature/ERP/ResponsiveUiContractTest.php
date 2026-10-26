<?php

namespace Tests\Feature\ERP;

use Tests\TestCase;

final class ResponsiveUiContractTest extends TestCase
{
    public function test_institutional_layout_loads_responsive_table_helper(): void
    {
        $layout = file_get_contents(resource_path('views/erp/_fallback-layout.blade.php'));
        $this->assertIsString($layout);
        $this->assertStringContainsString('responsive-tables.js', $layout);
        $this->assertStringContainsString('mobile-cards.css', $layout);
    }

    public function test_mobile_table_helper_labels_cells_and_empty_states(): void
    {
        $source = file_get_contents(base_path('public/fida-erp/responsive-tables.js'));
        $this->assertIsString($source);
        foreach (['.fida-table, .erp-table', 'erp-responsive-list', 'data-label', 'dataset.label', 'erp-empty-state', 'thead th'] as $marker) {
            $this->assertStringContainsString($marker, $source);
        }
    }

    public function test_mobile_card_styles_preserve_table_labels_and_empty_states(): void
    {
        $css = file_get_contents(base_path('public/fida-erp/mobile-cards.css'));
        $this->assertIsString($css);
        foreach (['max-width:600px', 'erp-responsive-list', 'attr(data-label)', 'erp-empty-state'] as $marker) {
            $this->assertStringContainsString($marker, $css);
        }
    }

    public function test_existing_institutional_navigation_remains_present(): void
    {
        $layout = file_get_contents(resource_path('views/erp/_fallback-layout.blade.php'));
        $this->assertStringContainsString('id="fida-rail"', $layout);
        $this->assertStringContainsString('id="fida-sidenav"', $layout);
        $this->assertStringContainsString('ui-shell.js', $layout);
    }
}
