<?php

namespace App\Enums;

enum TestResult: string
{
    case Ok = 'ok';
    case Failed = 'failed';
    case NotApplicable = 'na';
}
