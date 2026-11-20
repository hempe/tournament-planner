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

    /**
     * First error per field, in the API's field-error format.
     *
     * @return array<string, array{key: string, params?: array<string, scalar>}>
     */
    public function fieldErrors(): array
    {
        $fields = [];
        foreach ($this->errors as $error) {
            if (isset($fields[$error->field])) {
                continue;
            }
            $entry = ['key' => $error->key];
            if ($error->params !== []) {
                $entry['params'] = $error->params;
            }
            $fields[$error->field] = $entry;
        }
        return $fields;
    }
}
