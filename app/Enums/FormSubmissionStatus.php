<?php

namespace App\Enums;

enum FormSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
