<?php

namespace Tests\Unit;

use App\Enums\FormFieldType;
use PHPUnit\Framework\TestCase;

class FormFieldTypeTest extends TestCase
{
    public function test_required_platform_field_types_are_allowlisted(): void
    {
        foreach (['text', 'textarea', 'number', 'decimal', 'boolean', 'date', 'datetime', 'select', 'multi_select', 'radio', 'checkbox_group', 'user', 'asset', 'organization_unit', 'zone', 'geopoint', 'geometry', 'image', 'file', 'signature', 'repeatable_group'] as $type) {
            $this->assertContains($type, FormFieldType::values());
        }
    }
}
