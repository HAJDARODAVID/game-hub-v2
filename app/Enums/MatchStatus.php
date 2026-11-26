<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Playing = 'playing';
    case Finished = 'finished';   // won or drawn
}
