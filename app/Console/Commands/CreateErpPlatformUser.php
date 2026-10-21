<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
final class CreateErpPlatformUser extends Command
{
 protected $signature='erp:platform-user {email}';
 protected $description='Create a central Laravel platform administrator account without public registration';
 public function handle(): int {
  $email=$this->argument('email');if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Invalid email');return self::FAILURE;}
  if(DB::connection('mysql')->table('users')->where('email',$email)->exists()){$this->error('User already exists; use erp:platform-admin instead');return self::FAILURE;}
  $name=$this->ask('Administrator display name');$password=$this->secret('Administrator password (at least 12 characters)');
  if(strlen((string)$password)<12||!$name){$this->error('Invalid name or short password');return self::FAILURE;}
  DB::connection('mysql')->table('users')->insert(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),
      'erp_platform_admin'=>1,'email_verified_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $this->info('Platform administrator created. Configure ERP_PLATFORM_HOST and sign in at /platform/erp/login');
  return self::SUCCESS;
 }
}
