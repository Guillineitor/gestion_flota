<?php

namespace App\Enums;

/**
 * Tipos de equipo que gestiona la plataforma (camiones, excavadoras y generadores).
 *
 * El valor de cada caso es lo que se guarda en la base de datos
 * y lo que acepta/devuelve la API.
 */
enum TipoEquipo: string
{
    /** Vehículos de la flota. */

    case Camion = 'camion';

    case Excavadora = 'excavadora';

    case Generador = 'generador';
}