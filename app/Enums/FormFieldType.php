<?php

namespace App\Enums;

enum FormFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case Datetime = 'datetime';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Radio = 'radio';
    case CheckboxGroup = 'checkbox_group';
    case User = 'user';
    case Asset = 'asset';
    case OrganizationUnit = 'organization_unit';
    case Zone = 'zone';
    case Geopoint = 'geopoint';
    case Geometry = 'geometry';
    case Image = 'image';
    case File = 'file';
    case Signature = 'signature';
    case RepeatableGroup = 'repeatable_group';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
