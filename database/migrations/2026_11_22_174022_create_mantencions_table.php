<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de mantenciones.
     */
    public function up(): void
    {
        Schema::create('mantenciones', function (Blueprint $table) {
            $table->id();

            // Para no borrar un equipo con historial previo de mantenciones.
            $table->foreignId('equipo_id')->constrained('equipos')->restrictOnDelete();

            // Tipo de mantención.
            $table->string('tipo');

            // Fecha de la mantención.
            $table->date('fecha');

            // Horómetro del equipo al momento de la mantención.
            $table->decimal('horometro', 10, 1);

            // Costo de la mantención en pesos chilenos.
            $table->unsignedInteger('costo_clp');

            // Descripción de la mantención (opcional).
            $table->text('descripcion')->nullable();

            $table->timestamps();

            // Acelera la búsqueda de la última mantención preventiva.
            $table->index(['equipo_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenciones');
    }
};
