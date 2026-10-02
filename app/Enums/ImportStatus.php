<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';
}