<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @deprecated
 * @since 1.1.2
 * @todo [remove@1.6] Remove in sMultisite v1.6
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('s_multisites')) {
            Schema::table('s_multisites', function (Blueprint $table) {
                if (!Schema::hasColumn('s_multisites', 'site_color')) {
                    $table->string('site_color', 50)->after('unauthorized_page')->default('#60a5fa');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('s_multisites')) {
            Schema::table('s_multisites', function (Blueprint $table) {
                if (Schema::hasColumn('s_multisites', 'site_color')) {
                    $table->dropColumn('site_color');
                }
            });
        }
    }
};
