<?php

declare(strict_types=1);

namespace App;

enum State: string
{
    case BW = 'BW'; // Baden-Württemberg
    case BY = 'BY'; // Bayern
    case BE = 'BE'; // Berlin
    case BB = 'BB'; // Brandenburg
    case HB = 'HB'; // Bremen
    case HH = 'HH'; // Hamburg
    case HE = 'HE'; // Hessen
    case MV = 'MV'; // Mecklenburg-Vorpommern
    case NI = 'NI'; // Niedersachsen
    case NW = 'NW'; // Nordrhein-Westfalen
    case RP = 'RP'; // Rheinland-Pfalz
    case SL = 'SL'; // Saarland
    case SN = 'SN'; // Sachsen
    case ST = 'ST'; // Sachsen-Anhalt
    case SH = 'SH'; // Schleswig-Holstein
    case TH = 'TH'; // Thüringen
    case NONE = '';
}
