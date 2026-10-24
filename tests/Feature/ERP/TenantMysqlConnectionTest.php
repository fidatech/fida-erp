<?php

namespace Tests\Feature\ERP;

use App\Models\ERP\Central\Tenant;
use App\Services\ERP\TenantConnector;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uses a disposable MySQL service and only reads databases.
 * The CI workflow provisions databases and credentials.
 */
final class TenantMysqlConnectionTest extends TestCase
{
    public function test_tenant_connection_cannot_access_other_tenant_database(): void
    {
        if (getenv('ERP_RUN_ISOLATION_TESTS') !== '1') {
            $this->markTestSkipped('Requires disposable MySQL CI service.');
        }

        $this->assertSame('testing', app()->environment());
        $host = (string) getenv('ERP_TEST_DB_HOST');
        $this->assertContains($host, ['127.0.0.1', 'localhost'], 'MySQL host must be local to CI.');

        $databases = [
            (string) getenv('ERP_TEST_CENTRAL_DATABASE'),
            (string) getenv('ERP_TEST_TENANT_A_DATABASE'),
            (string) getenv('ERP_TEST_TENANT_B_DATABASE'),
        ];
        $this->assertCount(3, array_unique($databases));
        foreach ($databases as $database) {
            $this->assertMatchesRegularExpression('/^erp_test_[a-zA-Z0-9_]+$/D', $database);
        }

        $mysql = config('database.connections.mysql');
        $mysql['host'] = $host;
        $mysql['port'] = (string) getenv('ERP_TEST_DB_PORT');
        $mysql['database'] = $databases[0];
        $mysql['username'] = (string) getenv('ERP_TEST_CENTRAL_USER');
        $mysql['password'] = (string) getenv('ERP_TEST_CENTRAL_PASSWORD');
        config(['database.connections.mysql' => $mysql]);
        DB::purge('mysql');

        $connector = app(TenantConnector::class);
        try {
            foreach (['A', 'B'] as $suffix) {
                $tenant = new Tenant();
                $tenant->database_name = (string) getenv("ERP_TEST_TENANT_{$suffix}_DATABASE");
                $tenant->database_username = (string) getenv("ERP_TEST_TENANT_{$suffix}_USER");
                $tenant->database_password = (string) getenv("ERP_TEST_TENANT_{$suffix}_PASSWORD");
                $connector->connect($tenant);

                $actual = DB::connection('erp_tenant')->selectOne('SELECT DATABASE() AS db')->db;
                $this->assertSame($tenant->database_name, $actual);

                $other = $suffix === 'A' ? $databases[2] : $databases[1];
                try {
                    DB::connection('erp_tenant')->select("SELECT COUNT(*) AS total FROM `{$other}`.information_schema_dummy");
                    $this->fail('Tenant credentials unexpectedly accessed another tenant database.');
                } catch (\Illuminate\Database\QueryException $e) {
                    $this->assertSame('42000', (string) $e->errorInfo[0]);
                }
                $connector->disconnect();
            }
        } finally {
            $connector->disconnect();
            DB::purge('mysql');
        }
    }
}
