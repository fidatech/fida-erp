<?php
namespace App\Jobs;

use App\Services\ERP\AutomaticTenantProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessErpRegistration implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 160;
    public int $uniqueFor = 360;
    public function __construct(public readonly int $registrationId)
    {
        $this->onQueue('erp-provisioning');
    }
    public function uniqueId(): string
    {
        return 'erp-registration-'.$this->registrationId;
    }
    public function handle(AutomaticTenantProvisioner $provisioner): void
    {
        $provisioner->provision($this->registrationId);
    }
}
