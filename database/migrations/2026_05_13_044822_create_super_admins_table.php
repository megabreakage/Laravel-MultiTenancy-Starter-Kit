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
        Schema::connection('central')->create('super_admins', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('identifier')->unique();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('super_admins');
    }
};
