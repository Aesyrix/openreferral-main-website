<?php

namespace App\Enums;

enum NoteVisibility: string
{
    case INTERNAL = 'internal';
    case SHARED_WITH_AGENT = 'shared_with_agent';
}
