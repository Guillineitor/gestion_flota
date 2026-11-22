<?php

namespace App\Enums;

/**
 * Estado operativo de un equipo.
 */
enum EstadoEquipo: string
{
    /** Disponible para trabajar. */
    case Operativo = 'operativo';

    /** Detenido temporalmente por una mantención. */
    case EnMantencion = 'en_mantencion';

    /** No disponible hasta nuevo aviso. */
    case FueraDeServicio = 'fuera_de_servicio';
}