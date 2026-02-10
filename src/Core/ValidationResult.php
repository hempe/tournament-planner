<?php

declare(strict_types=1);

namespace TP\Core;

final class ValidationResult
{
    /** @param ValidationError[] $errors */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = []
    ) {
    }

    /** @return string[] */
    public function getErrorMessages(): array
    {
        return array_map(fn(ValidationError $error) => $error->message, $this->errors);
    }

    /** @return array<string, string[]> */
    public function getErrorsByField(): array
    {
        $result = [];
        foreach ($this->errors as $error) {
            $result[$error->field][] = $error->message;
        }
        return $result;
    }
}
