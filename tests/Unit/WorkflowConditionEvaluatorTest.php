<?php

namespace Tests\Unit;

use App\Services\WorkflowConditionEvaluator;
use DomainException;
use PHPUnit\Framework\TestCase;

class WorkflowConditionEvaluatorTest extends TestCase
{
    public function test_allowlisted_conditions_are_evaluated(): void
    {
        $evaluator = new WorkflowConditionEvaluator;
        $condition = ['all' => [
            ['field' => 'work_order.priority', 'operator' => 'in', 'value' => ['high', 'critical']],
            ['field' => 'input.approved', 'operator' => 'eq', 'value' => true],
            ['field' => 'subject.properties.cost', 'operator' => 'gte', 'value' => 100],
        ]];

        $this->assertTrue($evaluator->evaluate($condition, [
            'work_order' => ['priority' => 'high'], 'input' => ['approved' => true],
            'subject' => ['properties' => ['cost' => 125]], 'actor' => ['role_ids' => [1]],
        ]));
    }

    public function test_executable_or_deep_fields_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new WorkflowConditionEvaluator)->validate(['field' => 'subject.properties.customer.secret', 'operator' => 'eq', 'value' => 'x']);
    }
}
