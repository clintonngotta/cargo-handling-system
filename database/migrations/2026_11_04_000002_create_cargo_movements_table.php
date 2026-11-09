<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargo_movements', function (Blueprint $table) {
            $table->id();
            $table->string('external_reference')->unique();
            $table->string('direction', 16);
            $table->string('commodity', 8);
            $table->foreignId('storage_tank_id')->constrained()->restrictOnDelete();
            $table->string('counterparty');
            $table->unsignedBigInteger('gross_litres');
            $table->unsignedBigInteger('transit_loss_litres');
            $table->unsignedBigInteger('net_litres');
            $table->dateTime('occurred_at');
            $table->string('status', 16)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->unsignedBigInteger('balance_after_litres')->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->timestamps();

            $table->index(['storage_tank_id', 'status', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargo_movements');
    }
};
