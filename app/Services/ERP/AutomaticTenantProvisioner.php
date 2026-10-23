<?php
namespace App\Services\ERP;

use App\Models\ERP\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AutomaticTenantProvisioner
{
    public function __construct(
        private readonly CpanelMysqlProvisioner $cpanel,
        private readonly TenantBootstrapService $bootstrap,
        private readonly TenantDomainVerifier $domains,
        private readonly TenantConnector $connector,
    ) {}

    /**
     * Safe to retry: keep a unique provisioning run, deterministic MySQL names,
     * encrypted credentials and a persistent checkpoint after each external action.
     */
    public function provision(int $registrationId): void
    {
        Cache::lock('erp:registration-provision:'.$registrationId, 240)->block(2, function() use ($registrationId) {
            $this->provisionExclusively($registrationId);
        });
    }

    private function provisionExclusively(int $registrationId): void
    {
        if (!app()->environment(['staging','production','local','testing'])) {
            throw new RuntimeException('Provisioning is disabled in this environment');
        }
        $db = DB::connection(config('erp.central_connection','mysql'));
        $registration = $db->table('erp_registration_requests')->find($registrationId);
        if (!$registration) throw new RuntimeException('Registration not found');
        if ($registration->status === 'approved') return;
        if (!in_array($registration->status, ['reviewed','provisioning'],true)) {
            throw new RuntimeException('Administrator review required');
        }
        $prefix = $this->cpanel->prefix();
        if ($prefix !== 'fidaerp_') {
            throw new RuntimeException('Unexpected cPanel account prefix');
        }
        $run = $db->table('erp_provisioning_runs')->where('registration_id',$registrationId)->first();
        if (!$run) {
            $this->reserve($registration, $prefix);
        }
        $run = $db->table('erp_provisioning_runs')->where('registration_id',$registrationId)->first();
        if (!$run) throw new RuntimeException('Provisioning record unavailable');
        $tenant = Tenant::findOrFail($run->tenant_id);
        try {
            $db->table('erp_provisioning_runs')->where('id',$run->id)->update([
                'attempts'=>DB::raw('attempts+1'),'last_error'=>null,'updated_at'=>now()
            ]);
            if ($run->stage === 'reserved') {
                if ($this->cpanel->databaseExists($tenant->database_name) ||
                    $this->cpanel->userExists($tenant->database_username)) {
                    throw new RuntimeException('Namespace collision: reserved database or user already exists');
                }
                $this->cpanel->createDatabase($tenant->database_name);
                $this->advance($run->id,'database_created');
                $run->stage = 'database_created';
            }
            if ($run->stage === 'database_created') {
                if (!$this->cpanel->databaseExists($tenant->database_name) ||
                    $this->cpanel->userExists($tenant->database_username)) {
                    throw new RuntimeException('Unexpected cPanel database/user state');
                }
                $this->cpanel->createUser($tenant->database_username, $tenant->database_password);
                $this->advance($run->id,'user_created');
                $run->stage = 'user_created';
            }
            if ($run->stage === 'user_created') {
                $this->cpanel->grant($tenant->database_username,$tenant->database_name);
                $this->advance($run->id,'granted');
                $run->stage = 'granted';
            }
            if ($run->stage === 'granted') {
                $this->connector->connect($tenant);
                try {
                    $status = Artisan::call('migrate', [
                        '--database'=>'erp_tenant',
                        '--path'=>'database/migrations/erp_tenant',
                        '--force'=>true, '--no-interaction'=>true,
                    ]);
                    if ($status !== 0) throw new RuntimeException('Tenant migration failed');
                } finally {
                    $this->connector->disconnect();
                }
                $this->advance($run->id,'migrated');
                $run->stage = 'migrated';
            }
            if ($run->stage === 'migrated') {
                $this->connector->connect($tenant);
                try { $this->bootstrap->initialize($registration); }
                finally { $this->connector->disconnect(); }
                $this->advance($run->id,'seeded');
                $run->stage = 'seeded';
            }
            if (in_array($run->stage,['seeded','awaiting_domain'],true)) {
                if (!$this->domains->isReady($tenant->domain)) {
                    $db->transaction(function () use ($db,$tenant,$run) {
                        $db->table('erp_tenants')->where('id',$tenant->id)->update([
                            'status'=>'awaiting_domain','updated_at'=>now()
                        ]);
                        $db->table('erp_provisioning_runs')->where('id',$run->id)->update([
                            'stage'=>'awaiting_domain',
                            'last_error'=>'Tenant hostname requires DNS, web routing and valid HTTPS',
                            'updated_at'=>now(),
                        ]);
                    });
                    return;
                }
                $this->connector->connect($tenant);
                try {
                    $tenantDb = DB::connection('erp_tenant');
                    if ($tenantDb->table('users')->where('email',$registration->email)->count() !== 1
                        || $tenantDb->table('company_settings')->count() !== 1) {
                        throw new RuntimeException('Tenant initialization verification failed');
                    }
                } finally {
                    $this->connector->disconnect();
                }
                $db->transaction(function () use ($db,$tenant,$run,$registrationId) {
                    $db->table('erp_tenants')->where('id',$tenant->id)->update([
                        'status'=>'active','suspended_at'=>null,'trial_ends_at'=>now()->addDays(7),'updated_at'=>now()
                    ]);
                    $db->table('erp_registration_requests')->where('id',$registrationId)
                        ->where('status','provisioning')->update(['status'=>'approved','updated_at'=>now()]);
                    $db->table('erp_provisioning_runs')->where('id',$run->id)->update([
                        'stage'=>'active','last_error'=>null,'updated_at'=>now()
                    ]);
                    $db->table('erp_platform_audit_logs')->insert([
                        'actor'=>'queue:erp-provisioning','event'=>'registration.provisioned',
                        'tenant_id'=>$tenant->id,
                        'context'=>json_encode(['registration_id'=>$registrationId]),
                        'created_at'=>now(),'updated_at'=>now(),
                    ]);
                });
            }
        } catch (Throwable $e) {
            // No credentials, raw SQL errors or cPanel API output in platform-visible errors.
            $db->table('erp_provisioning_runs')->where('id',$run->id)->update([
                'last_error'=>'Provisioning failed at stage '.$run->stage.'; administrator retry required',
                'updated_at'=>now()
            ]);
            report($e);
            throw $e;
        }
    }

    private function reserve(object $registration, string $prefix): void
    {
        $db = DB::connection(config('erp.central_connection','mysql'));
        $db->transaction(function () use ($db,$registration,$prefix) {
            $row = $db->table('erp_registration_requests')->where('id',$registration->id)
                ->lockForUpdate()->first();
            if (!$row || !in_array($row->status,['reviewed','provisioning'],true)) {
                throw new RuntimeException('Registration is not approved for provisioning');
            }
            if ($db->table('erp_provisioning_runs')->where('registration_id',$row->id)->exists()) return;

            $slug = substr(Str::slug($row->company) ?: 'business',0,34).'-'.$row->id;
            $domain = $slug.'.'.strtolower((string)config('erp.platform_host'));
            $platformHost = parse_url((string)config('app.url'),PHP_URL_HOST);
            if (!preg_match('/^[a-z0-9][a-z0-9-]{1,48}$/D',$slug)
                || !preg_match('/^[a-z0-9.-]{4,253}$/D',$domain)
                || $domain === $platformHost
                || !str_ends_with($domain,'.'.$platformHost)) {
                throw new RuntimeException('Tenant hostname configuration invalid');
            }
            $database = $prefix.'e'.$row->id;
            $user = $prefix.'u'.$row->id;
            if ($db->table('erp_tenants')->where('slug',$slug)->orWhere('domain',$domain)
                ->orWhere('database_name',$database)->orWhere('database_username',$user)->exists()) {
                throw new RuntimeException('Tenant namespace already registered');
            }
            $tenant = Tenant::create([
                'business_name'=>$row->company,'slug'=>$slug,'domain'=>$domain,
                'database_name'=>$database,'database_username'=>$user,
                'database_password'=>Str::password(40, symbols:false),
                'status'=>'provisioning',
            ]);
            $db->table('erp_provisioning_runs')->insert([
                'registration_id'=>$row->id,'tenant_id'=>$tenant->id,
                'stage'=>'reserved','attempts'=>0,'last_error'=>null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $db->table('erp_registration_requests')->where('id',$row->id)->update([
                'status'=>'provisioning','updated_at'=>now()
            ]);
        },3);
    }

    private function advance(int $runId, string $stage): void
    {
        DB::connection(config('erp.central_connection','mysql'))
            ->table('erp_provisioning_runs')->where('id',$runId)
            ->update(['stage'=>$stage,'last_error'=>null,'updated_at'=>now()]);
    }
}
