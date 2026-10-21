<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
final class PromoteErpPlatformAdmin extends Command
{
 protected $signature='erp:platform-admin {email}';
 protected $description='Allow an existing, authenticated central Laravel user into the ERP platform console';
 public function handle(): int {
  if(!$this->confirm('Grant platform-wide tenant, subscription and payment administration privileges?',false))return self::FAILURE;
  $updated=DB::connection('mysql')->table('users')->where('email',$this->argument('email'))->update(['erp_platform_admin'=>1]);
  if(!$updated){$this->error('Central user not found');return self::FAILURE;}
  $this->info('Platform administrator promoted');return self::SUCCESS;
 }
}
