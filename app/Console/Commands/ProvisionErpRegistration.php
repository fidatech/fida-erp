<?php
namespace App\Console\Commands;

use App\Jobs\ProcessErpRegistration;
use App\Services\ERP\AutomaticTenantProvisioner;
use Illuminate\Console\Command;

final class ProvisionErpRegistration extends Command
{
    protected $signature = 'erp:registration-provision {registration_id : Approved registration numeric ID} {--sync : Process immediately instead of enqueuing}';
    protected $description = 'Retry an administrator-reviewed ERP registration safely';
    public function handle(AutomaticTenantProvisioner $provisioner): int
    {
        $id = (string)$this->argument('registration_id');
        if (!ctype_digit($id) || (int)$id < 1) {
            $this->error('Invalid registration ID');
            return self::FAILURE;
        }
        if ($this->option('sync')) {
            try {
                $provisioner->provision((int)$id);
                $this->info('Provisioning attempted; check the registration and domain status.');
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('Provisioning failed; check the private Laravel logs and provisioning status.');
                return self::FAILURE;
            }
        }
        ProcessErpRegistration::dispatch((int)$id);
        $this->info('Provisioning task queued.');
        return self::SUCCESS;
    }
}
