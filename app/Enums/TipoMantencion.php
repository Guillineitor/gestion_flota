<?php

namespace App\Enums;

/**
 * Tipos de mantención de un equipo.
 *
 * Solo las preventivas cuentan para calcular cuándo toca la
 * siguiente (ver el endpoint de mantención preventiva).
 */
enum TipoMantencion: string
{
    /** Programada por horas de uso, antes de que haya una falla. */
    case Preventiva = 'preventiva';

    /** Reparación de una falla ya ocurrida. */
    case Correctiva = 'correctiva';
}