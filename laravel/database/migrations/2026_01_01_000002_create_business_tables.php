<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('phone', 30)->nullable()->index();
            $t->string('email', 150)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('internet_packages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('slug', 80)->unique();
            $t->string('description')->nullable();
            $t->unsignedInteger('duration_minutes');           // validity once activated
            $t->unsignedBigInteger('data_limit_mb')->nullable(); // NULL = unlimited data
            $t->unsignedTinyInteger('max_devices')->default(1);
            $t->decimal('price', 12, 2)->default(0);
            $t->decimal('cost_price', 12, 2)->default(0);        // for profit analytics
            $t->char('currency', 3)->default('USD');
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['is_active', 'sort_order']);
        });

        Schema::create('bandwidth_limits', function (Blueprint $t) {
            $t->id();
            // scope: global (whole hotspot), package (defaults for a package),
            // token (override for one token), device (override for one device)
            $t->enum('scope', ['global', 'package', 'token', 'device']);
            $t->foreignId('internet_package_id')->nullable()->constrained('internet_packages')->cascadeOnDelete();
            $t->unsignedBigInteger('access_token_id')->nullable()->index();
            $t->unsignedBigInteger('network_device_id')->nullable()->index();
            $t->unsignedInteger('download_kbps')->nullable();   // NULL = no limit
            $t->unsignedInteger('upload_kbps')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['scope', 'is_active']);
        });

        Schema::create('token_batches', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->foreignId('internet_package_id')->constrained('internet_packages')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->boolean('is_promotional')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('printed_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->string('receipt_no', 30)->unique();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('sold_by')->constrained('users')->restrictOnDelete(); // sales officer
            $t->decimal('total_amount', 12, 2);
            $t->char('currency', 3)->default('USD');
            $t->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('pending');
            $t->timestamp('sold_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'sold_at']);
            $t->index('sold_by');
        });

        Schema::create('sales_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $t->foreignId('internet_package_id')->constrained('internet_packages')->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('unit_cost', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2);
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $t->enum('method', ['cash', 'mobile_money', 'bank_transfer', 'online']);
            $t->string('provider', 40)->nullable();            // e.g. paynow, ecocash
            $t->enum('status', ['pending', 'paid', 'failed', 'cancelled', 'refunded'])->default('pending');
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3)->default('USD');
            $t->string('reference', 80)->nullable();            // our reference
            $t->string('provider_reference', 120)->nullable();  // provider's transaction id
            $t->json('provider_payload')->nullable();
            $t->timestamp('verified_at')->nullable();           // set ONLY by verified confirmation
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'method']);
            $t->unique(['provider', 'provider_reference']);
        });

        Schema::create('access_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('token_batch_id')->nullable()->constrained('token_batches')->nullOnDelete();
            $t->foreignId('internet_package_id')->constrained('internet_packages')->restrictOnDelete();
            $t->foreignId('sales_item_id')->nullable()->constrained('sales_items')->nullOnDelete();
            // HMAC-SHA256(code, pepper): lookup key. Plain code is never stored.
            $t->char('code_hash', 64)->unique();
            // Encrypted copy kept only until the voucher is sold/printed, then purged.
            $t->text('code_encrypted')->nullable();
            $t->char('code_hint', 4);                           // last 4 chars for display
            $t->enum('status', ['unused', 'active', 'expired', 'suspended', 'exhausted', 'revoked'])->default('unused');
            $t->boolean('is_promotional')->default(false);
            // Snapshot of package terms at generation time (custom/overridden values allowed)
            $t->unsignedInteger('duration_minutes');
            $t->unsignedBigInteger('data_limit_bytes')->nullable();
            $t->unsignedTinyInteger('max_devices')->default(1);
            $t->unsignedBigInteger('data_used_bytes')->default(0);
            $t->string('restricted_mac', 17)->nullable();       // device-restricted token
            $t->timestamp('valid_until')->nullable();           // voucher must be redeemed before this
            $t->timestamp('activated_at')->nullable();          // first use
            $t->timestamp('expires_at')->nullable();            // activated_at + duration
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('revoke_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'expires_at']);
        });

        Schema::table('bandwidth_limits', function (Blueprint $t) {
            $t->foreign('access_token_id')->references('id')->on('access_tokens')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_limits', fn (Blueprint $t) => $t->dropForeign(['access_token_id']));
        foreach (['access_tokens', 'payments', 'sales_items', 'sales', 'token_batches',
                  'bandwidth_limits', 'internet_packages', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
