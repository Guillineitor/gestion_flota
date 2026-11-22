<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de cargas de combustible.
     */
    public function up(): void
    {
        Schema::create('cargas_combustible', function (Blueprint $table) {
            $table->id();

            // Para no borrar un equipo con historial previo.
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();


            // Fecha y hora de la carga.
            $table->dateTime('fecha_hora');

            // Litros cargados.
            $table->decimal('litros', 8, 2);

            // Monto de la carga de combustible en pesos chilenos.
            $table->unsignedInteger('monto_clp');

            // Horómetro del equipo al momento de cargar.
            $table->decimal('horometro', 10, 1);

            $table->timestamps();

            // Acelera el reporte por equipo y rango de fechas.
            $table->index(['equipo_id', 'fecha_hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_combustible');
    }
};
