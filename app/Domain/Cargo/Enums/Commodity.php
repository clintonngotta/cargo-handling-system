<?php

declare(strict_types=1);

namespace App\Domain\Cargo\Enums;

enum Commodity: string
{
    case CrudePalmOil = 'CPO';
    case CrudePalmOlein = 'CPOL';
    case PalmFattyAcidDistillate = 'PFAD';

    public function label(): string
    {
        return match ($this) {
            self::CrudePalmOil => 'Crude Palm Oil',
            self::CrudePalmOlein => 'Crude Palm Olein',
            self::PalmFattyAcidDistillate => 'Palm Fatty Acid Distillate',
        };
    }

    public function handlingNotes(): string
    {
        return match ($this) {
            self::CrudePalmOil => 'Unrefined and highly viscous. Requires constant temperature control.',
            self::CrudePalmOlein => 'Liquid fraction with higher fluidity. Used in cooking oil formulations.',
            self::PalmFattyAcidDistillate => 'Viscous by-product. Used in soap-making and animal feed.',
        };
    }

    public function requiresTemperatureControl(): bool
    {
        return $this === self::CrudePalmOil;
    }
}
