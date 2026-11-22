<?php

namespace App\Enums;

/**
 * Tipos de equipo que gestiona la plataforma.
 *
 * El valor de cada caso es lo que se guarda en la base de datos
 * y lo que acepta/devuelve la API.
 */
enum TipoEquipo: string
{
    /** Vehículo de transporte. */
    case Camion = 'camion';

    /** Vehículo de movimiento de tierra. */
    case Excavadora = 'excavadora';
}