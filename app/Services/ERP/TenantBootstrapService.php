<?php
namespace App\Services\ERP;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Seeds real business defaults only in a fresh, isolated tenant database. */
final class TenantBootstrapService
{
    public function initialize(object $registration): void
    {
        $db = DB::connection('erp_tenant');
        $db->transaction(function () use ($db, $registration) {
            if ($db->table('company_settings')->exists() || $db->table('users')->exists()) {
                if ($db->table('company_settings')->count() === 1
                    && $db->table('users')->where('email', $registration->email)->exists()
                    && $db->table('accounts')->where('code','1100')->exists()) {
                    return; // Idempotent retry after a fully committed bootstrap.
                }
                throw new RuntimeException('Tenant database contains partial or existing business data');
            }
            $now = now();
            $db->table('company_settings')->insert([
                'name' => $registration->company, 'base_currency' => 'AFN',
                'locale' => 'en', 'timezone' => 'Asia/Kabul',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $branchId = $db->table('branches')->insertGetId([
                'code' => 'HQ', 'name' => 'Head Office', 'active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ([
                ['AFN','Afghan Afghani',2], ['USD','US Dollar',2],
                ['PKR','Pakistani Rupee',2], ['IRR','Iranian Rial',0],
                ['EUR','Euro',2], ['AED','UAE Dirham',2],
            ] as [$code,$name,$precision]) {
                $db->table('currencies')->insert([
                    'code'=>$code,'name'=>$name,'minor_units'=>$precision,'active'=>1,
                ]);
            }
            $accounts = [
                ['1000','Assets','asset',null,0],
                ['1100','Trade Receivables','asset','1000',1],
                ['1200','Cash','asset','1000',1],
                ['1300','Inventory','asset','1000',1],
                ['2000','Liabilities','liability',null,0],
                ['2100','Trade Payables','liability','2000',1],
                ['2200','Sales Tax Payable','liability','2000',1],
                ['3000','Equity','equity',null,0],
                ['3100','Contributed Capital','equity','3000',1],
                ['4000','Income','income',null,0],
                ['4100','Sales Revenue','income','4000',1],
                ['5000','Expenses','expense',null,0],
                ['5100','Cost of Goods Sold','expense','5000',1],
            ];
            $ids = [];
            foreach ($accounts as [$code,$name,$type,$parent,$postable]) {
                $ids[$code] = $db->table('accounts')->insertGetId([
                    'code'=>$code,'name'=>$name,'type'=>$type,
                    'parent_id'=>$parent ? $ids[$parent] : null,
                    'is_postable'=>$postable,'active'=>1,
                    'created_at'=>$now,'updated_at'=>$now,
                ]);
            }
            $year = now()->year;
            $db->table('fiscal_periods')->insert([
                'name'=>(string)$year,'starts_on'=>$year.'-01-01',
                'ends_on'=>$year.'-12-31','is_closed'=>0,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $db->table('warehouses')->insert([
                'branch_id'=>$branchId,'code'=>'MAIN',
                'name'=>'Main Warehouse','created_at'=>$now,'updated_at'=>$now,
            ]);
            $owner = $db->table('roles')->insertGetId([
                'name'=>'owner','created_at'=>$now,'updated_at'=>$now,
            ]);
            $permissions = [
                'dashboard.view','customers.manage','products.manage','stock.manage',
                'stock.transfer','sales.manage','sales.post','reports.view',
                'accounts.view','accounts.post','settings.manage',
            ];
            foreach ($permissions as $permission) {
                $id = $db->table('permissions')->insertGetId(['name'=>$permission]);
                $db->table('permission_role')->insert([
                    'role_id'=>$owner,'permission_id'=>$id,
                ]);
            }
            $userId = $db->table('users')->insertGetId([
                'name'=>$registration->name,'email'=>$registration->email,
                'password'=>$registration->password_hash,'locale'=>'en',
                'active'=>1,'created_at'=>$now,'updated_at'=>$now,
            ]);
            $db->table('role_user')->insert([
                'role_id'=>$owner,'user_id'=>$userId,
            ]);
        }, 3);
    }
}
