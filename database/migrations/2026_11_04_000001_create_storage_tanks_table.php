<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_tanks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('commodity', 8)->index();
            $table->unsignedBigInteger('capacity_litres');
            $table->unsignedBigInteger('current_volume_litres')->default(0);
            $table->timestamps();
        });

        // Last line of defence against writes that bypass the application.
        // SQLite cannot add a constraint after creating a table, so it relies on the domain rule.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement(
                'ALTER TABLE storage_tanks ADD CONSTRAINT storage_tanks_volume_within_capacity '
                .'CHECK (current_volume_litres <= capacity_litres)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_tanks');
    }
};
