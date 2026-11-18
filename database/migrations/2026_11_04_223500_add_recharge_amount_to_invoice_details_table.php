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
        Schema::table('invoice_details', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_details', 'recharge_amount')) {
                $table->decimal('recharge_amount', 12, 2)->default(0)->nullable()->after('user_count');
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
        Schema::table('invoice_details', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_details', 'recharge_amount')) {
                $table->dropColumn('recharge_amount');
            }
        });
    }
};

