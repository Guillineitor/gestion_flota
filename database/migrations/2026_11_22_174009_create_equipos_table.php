<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de equipos.
     */
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();

            // Código interno de la empresa (único).
            $table->string('codigo')->unique();

            // Tipo de equipo.
            $table->string('tipo');

            // Patente del equipo (no necesariamente todos lo tienen).
            $table->string('patente')->nullable();

            // Marca del equipo.
            $table->string('marca');

            // Modelo del equipo.
            $table->string('modelo');

            // Último horómetro registrado. Un decimal porque los
            // horómetros marcan décimas de hora.
            $table->decimal('horometro_actual', 10, 1)->default(0);

            // Estado del equipo.
            $table->string('estado')->default('operativo');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
