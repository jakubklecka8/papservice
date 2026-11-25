<?php

namespace App\Enums;

enum ServiceType: string
{
    case Servis = 'servis';
    case Profilaxe = 'profilaxe';
    case Udrzba = 'udrzba';
    case Instalace = 'instalace';
}
