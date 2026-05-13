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
        Schema::connection('central')->table('oauth_clients', function (Blueprint $t) {
            $t->string('tenant_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('oauth_clients', function (Blueprint $t) {
            $t->dropColumn('tenant_id');
        });
    }
};
