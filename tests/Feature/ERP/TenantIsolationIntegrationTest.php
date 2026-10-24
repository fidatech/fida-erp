<?php

namespace Tests\Feature\ERP;

use Tests\TestCase;

/**
 * FERP-011: The previous integration fixture was unsafe because tenant
 * connection purges could invalidate open rollback transactions.
 * Run full isolation tests only with externally provisioned and disposable
 * MySQL databases. This placeholder cannot be mistaken for a passing test.
 */
final class TenantIsolationIntegrationTest extends TestCase
{
    public function test_disposable_mysql_fixture_is_required(): void
    {
        $this->markTestIncomplete('FERP-011 requires an isolated MySQL test runner.');
    }
}
