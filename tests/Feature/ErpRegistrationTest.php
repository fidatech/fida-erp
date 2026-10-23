<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessErpRegistration;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ErpRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('erp.central_connection', 'erp_registration_test');
        config()->set('database.connections.erp_registration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('erp_registration_test');
        Schema::connection('erp_registration_test')->create('erp_registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('company');
            $table->string('phone');
            $table->string('country');
            $table->string('password_hash');
            $table->string('status');
            $table->string('verification_token_hash')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('verification_sent_at')->nullable();
            $table->timestamps();
        });
        RateLimiter::clear('erp-registration:127.0.0.1');
        RateLimiter::clear('erp-resend:127.0.0.1');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        DB::disconnect('erp_registration_test');
        parent::tearDown();
    }

    private function registration(string $email): array
    {
        return [
            'name' => 'Business Owner',
            'email' => $email,
            'company' => 'Test Trading',
            'phone' => '0700000000',
            'country' => 'Afghanistan',
            'password' => 'LongTestPassword123!',
            'password_confirmation' => 'LongTestPassword123!',
            'terms' => '1',
        ];
    }

    public function test_registration_normalizes_email_and_hashes_password(): void
    {
        $this->post(route('erp.register.submit'), $this->registration('Owner@Example.COM'))
            ->assertRedirect(route('erp.register'));

        $row = DB::connection('erp_registration_test')->table('erp_registration_requests')->first();
        $this->assertSame('owner@example.com', $row->email);
        $this->assertSame('pending', $row->status);
        $this->assertNotSame('LongTestPassword123!', $row->password_hash);
        $this->assertTrue(password_verify('LongTestPassword123!', $row->password_hash));
    }

    public function test_reviewed_registration_cannot_be_submitted_again(): void
    {
        $db = DB::connection('erp_registration_test');
        $db->table('erp_registration_requests')->insert([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'company' => 'Existing',
            'phone' => '0700000000',
            'country' => 'Afghanistan',
            'password_hash' => 'existing-hash',
            'status' => 'reviewed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from(route('erp.register'))
            ->post(route('erp.register.submit'), $this->registration('OWNER@example.com'))
            ->assertRedirect(route('erp.register'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, $db->table('erp_registration_requests')->count());
    }
    public function test_registration_rejects_short_password_and_unaccepted_terms(): void
    {
        $payload = $this->registration('new@example.com');
        $payload['password'] = 'short';
        $payload['password_confirmation'] = 'short';
        $payload['terms'] = '0';

        $this->post(route('erp.register.submit'), $payload)
            ->assertSessionHasErrors(['password', 'terms']);

        $this->assertSame(0, DB::connection('erp_registration_test')
            ->table('erp_registration_requests')->count());
    }

    public function test_registration_rate_limit_blocks_fourth_submission(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('erp.register.submit'), $this->registration("owner{$attempt}@example.com"))
                ->assertRedirect(route('erp.register'));
        }

        $this->from(route('erp.register'))
            ->post(route('erp.register.submit'), $this->registration('owner4@example.com'))
            ->assertSessionHasErrors('email');

        $this->assertSame(3, DB::connection('erp_registration_test')
            ->table('erp_registration_requests')->count());
    }

    public function test_registration_generates_expiring_hashed_verification_token(): void
    {
        $this->post(route('erp.register.submit'), $this->registration('verified@example.com'))
            ->assertRedirect(route('erp.register'));
        $row = DB::connection('erp_registration_test')->table('erp_registration_requests')->first();
        $this->assertNotNull($row->verification_token_hash);
        $this->assertSame(64, strlen($row->verification_token_hash));
        $this->assertNotNull($row->verification_expires_at);
    }

    public function test_verification_rejects_invalid_and_expired_tokens(): void
    {
        $this->post(route('erp.register.submit'), $this->registration('verify@example.com'));
        $db = DB::connection('erp_registration_test');
        $row = $db->table('erp_registration_requests')->first();
        $this->get(route('erp.register.verify',['id'=>$row->id,'token'=>str_repeat('x',64)]))
            ->assertSessionHasErrors('email');
        $this->assertSame('pending',$db->table('erp_registration_requests')->first()->status);
        $db->table('erp_registration_requests')->where('id',$row->id)->update([
            'verification_token_hash'=>hash('sha256',str_repeat('a',64)),
            'verification_expires_at'=>now()->subMinute(),
        ]);
        $this->get(route('erp.register.verify',['id'=>$row->id,'token'=>str_repeat('a',64)]))
            ->assertSessionHasErrors('email');
        $this->assertSame('pending',$db->table('erp_registration_requests')->first()->status);
    }

    public function test_valid_verification_queues_provisioning_once(): void
    {
        Bus::fake();
        $this->post(route('erp.register.submit'), $this->registration('verify@example.com'));
        $db=DB::connection('erp_registration_test');
        $row=$db->table('erp_registration_requests')->first();
        $token=str_repeat('b',64);
        $db->table('erp_registration_requests')->where('id',$row->id)->update([
            'verification_token_hash'=>hash('sha256',$token),
            'verification_expires_at'=>now()->addMinutes(30),
        ]);
        $url=route('erp.register.verify',['id'=>$row->id,'token'=>$token]);
        $this->get($url)->assertSessionHas('success');
        $this->assertSame('provisioning',$db->table('erp_registration_requests')->first()->status);
        $this->assertNotNull($db->table('erp_registration_requests')->first()->email_verified_at);
        Bus::assertDispatched(ProcessErpRegistration::class,1);
        $this->get($url)->assertSessionHasErrors('email');
        Bus::assertDispatched(ProcessErpRegistration::class,1);
    }

}
