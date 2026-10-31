<?php

namespace App\Services;

use DomainException;

class WorkflowConditionEvaluator
{
    private const OPERATORS = ['eq', 'neq', 'in', 'not_in', 'truthy', 'falsy', 'gt', 'gte', 'lt', 'lte', 'exists', 'not_exists'];

    private const ROOTS = ['subject', 'work_order', 'actor', 'input'];

    public function validate(?array $definition, int $depth = 0): void
    {
        if ($definition === null || $definition === []) {
            return;
        }
        if ($depth > 5) {
            throw new DomainException('عمق شرط گردش کار بیش از حد مجاز است.');
        }

        foreach (['all', 'any'] as $group) {
            if (array_key_exists($group, $definition)) {
                if (! is_array($definition[$group]) || $definition[$group] === []) {
                    throw new DomainException("{$group} باید آرایه‌ای غیرخالی از شرط‌ها باشد.");
                }
                foreach ($definition[$group] as $condition) {
                    if (! is_array($condition)) {
                        throw new DomainException('ساختار شرط معتبر نیست.');
                    }
                    $this->validate($condition, $depth + 1);
                }

                return;
            }
        }
        if (array_key_exists('not', $definition)) {
            if (! is_array($definition['not'])) {
                throw new DomainException('ساختار not معتبر نیست.');
            }
            $this->validate($definition['not'], $depth + 1);

            return;
        }

        $field = $definition['field'] ?? null;
        $operator = $definition['operator'] ?? null;
        if (! is_string($field) || ! $this->validField($field) || ! in_array($operator, self::OPERATORS, true)) {
            throw new DomainException('فیلد یا عملگر شرط گردش کار مجاز نیست.');
        }
        if (in_array($operator, ['in', 'not_in'], true) && ! is_array($definition['value'] ?? null)) {
            throw new DomainException('عملگر عضویت به مقدار آرایه‌ای نیاز دارد.');
        }
    }

    public function evaluate(?array $definition, array $context): bool
    {
        $this->validate($definition);
        if ($definition === null || $definition === []) {
            return true;
        }
        if (isset($definition['all'])) {
            return collect($definition['all'])->every(fn (array $item) => $this->evaluate($item, $context));
        }
        if (isset($definition['any'])) {
            return collect($definition['any'])->contains(fn (array $item) => $this->evaluate($item, $context));
        }
        if (isset($definition['not'])) {
            return ! $this->evaluate($definition['not'], $context);
        }

        [$exists, $actual] = $this->resolve($definition['field'], $context);
        $expected = $definition['value'] ?? null;

        return match ($definition['operator']) {
            'exists' => $exists,
            'not_exists' => ! $exists,
            'truthy' => $exists && (bool) $actual,
            'falsy' => ! $exists || ! (bool) $actual,
            'eq' => $exists && $actual === $expected,
            'neq' => ! $exists || $actual !== $expected,
            'in' => $exists && (is_array($actual) ? array_intersect($actual, $expected) !== [] : in_array($actual, $expected, true)),
            'not_in' => ! $exists || (is_array($actual) ? array_intersect($actual, $expected) === [] : ! in_array($actual, $expected, true)),
            'gt' => $this->numeric($actual, $expected, fn ($a, $b) => $a > $b),
            'gte' => $this->numeric($actual, $expected, fn ($a, $b) => $a >= $b),
            'lt' => $this->numeric($actual, $expected, fn ($a, $b) => $a < $b),
            'lte' => $this->numeric($actual, $expected, fn ($a, $b) => $a <= $b),
        };
    }

    private function validField(string $field): bool
    {
        $parts = explode('.', $field);
        if (! in_array($parts[0] ?? null, self::ROOTS, true) || count($parts) < 2 || count($parts) > 3) {
            return false;
        }
        if (! collect($parts)->every(fn ($part) => preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $part) === 1)) {
            return false;
        }

        return match ($parts[0]) {
            'actor' => $parts === ['actor', 'role_ids'],
            'work_order' => in_array($parts[1], ['priority', 'status', 'properties'], true) && ($parts[1] === 'properties' || count($parts) === 2),
            'subject' => $parts[1] === 'properties' && count($parts) === 3,
            'input' => count($parts) === 2,
        };
    }

    private function resolve(string $field, array $context): array
    {
        $value = $context;
        foreach (explode('.', $field) as $part) {
            if (! is_array($value) || ! array_key_exists($part, $value)) {
                return [false, null];
            }
            $value = $value[$part];
        }

        return [true, $value];
    }

    private function numeric(mixed $actual, mixed $expected, callable $comparison): bool
    {
        return is_numeric($actual) && is_numeric($expected) && $comparison((float) $actual, (float) $expected);
    }
}
