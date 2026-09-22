<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Validation de formulaire.
 *
 * Volontairement minimale : elle rassemble des messages par champ, en
 * français, prêts à être affichés tels quels sous le champ concerné.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $values = [];

    /** @param array<string, mixed> $input */
    public function __construct(private array $input)
    {
        foreach ($input as $key => $value) {
            $this->values[(string) $key] = is_string($value) ? trim($value) : '';
        }
    }

    public function value(string $field): string
    {
        return $this->values[$field] ?? '';
    }

    /** @return array<string, string> Toutes les valeurs saisies, pour réaffichage. */
    public function values(): array
    {
        return $this->values;
    }

    public function required(string $field, string $message): self
    {
        if ($this->value($field) === '') {
            $this->fail($field, $message);
        }

        return $this;
    }

    public function email(string $field, string $message): self
    {
        $value = $this->value($field);

        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, $message);
        }

        return $this;
    }

    public function pattern(string $field, string $regex, string $message): self
    {
        $value = $this->value($field);

        if ($value !== '' && preg_match($regex, $value) !== 1) {
            $this->fail($field, $message);
        }

        return $this;
    }

    public function maxLength(string $field, int $max, string $message): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->fail($field, $message);
        }

        return $this;
    }

    /** @param array<int, string> $allowed */
    public function inList(string $field, array $allowed, string $message): self
    {
        if (!in_array($this->value($field), $allowed, true)) {
            $this->fail($field, $message);
        }

        return $this;
    }

    /** Règle appliquée seulement si la condition est vraie. */
    public function when(bool $condition, callable $rules): self
    {
        if ($condition) {
            $rules($this);
        }

        return $this;
    }

    /** Le premier message l'emporte : on ne noie pas l'utilisateur sous les doublons. */
    public function fail(string $field, string $message): self
    {
        $this->errors[$field] ??= $message;

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
