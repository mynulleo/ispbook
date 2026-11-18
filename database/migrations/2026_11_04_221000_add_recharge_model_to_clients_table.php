<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'recharge_model')) {
                $table->enum('recharge_model', ['paybill', 'commission'])->default('paybill')->nullable()->after('package_id');
            }
            if (!Schema::hasColumn('clients', 'percentage')) {
                $table->decimal('percentage', 8, 2)->default(0)->nullable()->after('package_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'recharge_model')) {
                $table->dropColumn('recharge_model');
            }
            if (Schema::hasColumn('clients', 'percentage')) {
                $table->dropColumn('percentage');
            }
        });
    }
};

