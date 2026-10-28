<?php

namespace Pkly\QueryHelper;

/**
 * Describes a `NEW Class(...)` DQL expression. Fields are passed positionally to the constructor
 * of $class and may themselves be another Model, which produces a nested NEW expression.
 *
 * @template-covariant TModel of object
 */
final class Model
{
    /**
     * @param class-string<TModel>          $class
     * @param non-empty-list<string|Model<object>> $fields
     */
    private function __construct(
        private readonly string $class,
        private readonly array $fields,
    ) {
    }

    /**
     * @template TOf of object
     *
     * @param class-string<TOf>                    $class
     * @param non-empty-list<string|Model<object>> $fields
     *
     * @return Model<TOf>
     */
    public static function of(
        string $class,
        array $fields
    ): self {
        return new self($class, $fields);
    }

    /**
     * @return class-string<TModel>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return non-empty-list<string|Model<object>>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
