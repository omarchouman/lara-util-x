<?php

namespace LaraUtilX\Tests\Unit\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LaraUtilX\Tests\TestCase;
use LaraUtilX\Traits\Auditable;

class AuditableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();

        Schema::create('audit_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('nickname')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_accounts');

        parent::tearDown();
    }

    private function latestAudit(): object
    {
        return DB::table('model_audits')->orderByDesc('id')->first();
    }

    // -----------------------------------------------------------------------
    // Excluded attributes
    // -----------------------------------------------------------------------

    public function test_create_audit_excludes_sensitive_attributes()
    {
        AuditAccount::create([
            'name' => 'Ada',
            'password' => 'hashed-secret',
            'remember_token' => 'token-value',
        ]);

        $newValues = json_decode($this->latestAudit()->new_values, true);

        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertArrayNotHasKey('remember_token', $newValues);
        $this->assertEquals('Ada', $newValues['name']);
    }

    public function test_update_audit_excludes_sensitive_attributes_from_both_sides()
    {
        $account = AuditAccount::create([
            'name' => 'Ada',
            'password' => 'old-secret',
        ]);

        $account->update(['name' => 'Grace', 'password' => 'new-secret']);

        $audit = $this->latestAudit();
        $oldValues = json_decode($audit->old_values, true);
        $newValues = json_decode($audit->new_values, true);

        $this->assertEquals('updated', $audit->event);
        $this->assertArrayNotHasKey('password', $oldValues);
        $this->assertArrayNotHasKey('password', $newValues);
        $this->assertEquals('Grace', $newValues['name']);
    }

    public function test_delete_audit_excludes_sensitive_attributes()
    {
        $account = AuditAccount::create([
            'name' => 'Ada',
            'password' => 'hashed-secret',
        ]);

        $account->delete();

        $audit = $this->latestAudit();

        $this->assertEquals('deleted', $audit->event);
        $this->assertArrayNotHasKey('password', json_decode($audit->old_values, true));
    }

    public function test_non_sensitive_attributes_are_still_recorded()
    {
        AuditAccount::create(['name' => 'Ada', 'nickname' => 'countess']);

        $newValues = json_decode($this->latestAudit()->new_values, true);

        $this->assertEquals('countess', $newValues['nickname']);
    }

    // -----------------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------------

    public function test_model_can_exclude_additional_attributes()
    {
        SecretiveAuditAccount::create(['name' => 'Ada', 'nickname' => 'countess']);

        $newValues = json_decode($this->latestAudit()->new_values, true);

        $this->assertArrayNotHasKey('nickname', $newValues);
        $this->assertEquals('Ada', $newValues['name']);
    }

    public function test_excluded_attributes_are_configurable()
    {
        Config::set('lara-util-x.audit.excluded_attributes', ['name']);

        AuditAccount::create(['name' => 'Ada', 'nickname' => 'countess']);

        $newValues = json_decode($this->latestAudit()->new_values, true);

        $this->assertArrayNotHasKey('name', $newValues);
        $this->assertEquals('countess', $newValues['nickname']);
    }

    public function test_audit_table_name_is_configurable()
    {
        Schema::create('custom_audits', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Config::set('lara-util-x.audit.table', 'custom_audits');

        AuditAccount::create(['name' => 'Ada']);

        $this->assertEquals(1, DB::table('custom_audits')->count());
        $this->assertEquals(0, DB::table('model_audits')->count());

        Schema::dropIfExists('custom_audits');
    }
}

class AuditAccount extends Model
{
    use Auditable;

    protected $table = 'audit_accounts';
    protected $guarded = [];
}

class SecretiveAuditAccount extends AuditAccount
{
    protected static function auditExcludedAttributes(): array
    {
        return array_merge(parent::auditExcludedAttributes(), ['nickname']);
    }
}
