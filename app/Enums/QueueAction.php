<?php

namespace App\Enums;

enum QueueAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Photo = 'photo';
    case Move = 'move';
}