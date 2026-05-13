<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('failed_jobs', function (Blueprint $t) {
            $t->string('tenant_id')->nullable()->after('queue')->index();
            $t->unsignedBigInteger('acting_user_id')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('failed_jobs', function (Blueprint $t) {
            $t->dropColumn(['tenant_id', 'acting_user_id']);
        });
    }
};
