<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Validateur de données simple inspiré de la syntaxe Laravel.
 *
 * Règles supportées :
 *   required, email, min:N, max:N, between:N,M, numeric, integer,
 *   in:a,b,c, regex:/.../, date, unique:table,column[,exceptId,column],
 *   exists:table,column, confirmed
 */
final class Validator
{
    private array $errors = [];

    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = [],
        private array $labels = []
    ) {}

    public static function make(array $data, array $rules, array $messages = [], array $labels = []): self
    {
        return new self($data, $rules, $messages, $labels);
    }

    public function validate(): array
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }
        return array_intersect_key($this->data, $this->rules);
    }

    public function fails(): bool { return !empty($this->errors); }
    public function errors(): array { return $this->errors; }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        $params = [];
        if (str_contains($rule, ':')) {
            [$rule, $paramStr] = explode(':', $rule, 2);
            $params = explode(',', $paramStr);
        }

        $label = $this->labels[$field] ?? $field;
        $isEmpty = $value === null || $value === '' || (is_array($value) && empty($value));

        if ($rule !== 'required' && $isEmpty) return;

        switch ($rule) {
            case 'required':
                if ($isEmpty) $this->addError($field, "Le champ $label est obligatoire.");
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) $this->addError($field, "Le $label doit être une adresse email valide.");
                break;
            case 'min':
                $min = (int)$params[0];
                    if (is_array($value) ? count($value) < $min : ((is_int($value) || is_float($value)) ? $value < $min : mb_strlen((string)$value) < $min))
                    $this->addError($field, "Le $label doit contenir au moins $min caractères.");
                break;
            case 'max':
                $max = (int)$params[0];
                    if (is_array($value) ? count($value) > $max : ((is_int($value) || is_float($value)) ? $value > $max : mb_strlen((string)$value) > $max))
                    $this->addError($field, "Le $label ne peut dépasser $max caractères.");
                break;
            case 'numeric':
                if (!is_numeric($value)) $this->addError($field, "Le $label doit être numérique.");
                break;
            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) $this->addError($field, "Le $label doit être un entier.");
                break;
            case 'in':
                if (!in_array((string)$value, $params, true)) $this->addError($field, "Le $label est invalide.");
                break;
            case 'regex':
                $pattern = implode(':', $params);
                if (!preg_match($pattern, (string)$value)) $this->addError($field, "Le $label a un format invalide.");
                break;
            case 'date':
                if (!strtotime((string)$value)) $this->addError($field, "Le $label doit être une date valide.");
                break;
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if (($this->data[$confirmField] ?? null) !== $value)
                    $this->addError($field, "La confirmation du $label ne correspond pas.");
                break;
            case 'unique':
                $table = $params[0]; $column = $params[1] ?? $field;
                $exceptId = $params[2] ?? null; $exceptCol = $params[3] ?? 'id';
                $sql = "SELECT 1 FROM $table WHERE $column = ?";
                $args = [$value];
                if ($exceptId !== null) { $sql .= " AND $exceptCol != ?"; $args[] = $exceptId; }
                $sql .= ' LIMIT 1';
                if (Database::selectOne($sql, $args)) $this->addError($field, "Le $label est déjà utilisé.");
                break;
            case 'exists':
                $table = $params[0]; $column = $params[1] ?? $field;
                if (!Database::selectOne("SELECT 1 FROM $table WHERE $column = ? LIMIT 1", [$value]))
                    $this->addError($field, "Le $label sélectionné est invalide.");
                break;
        }
    }

    private function addError(string $field, string $msg): void
    {
        $this->errors[$field][] = $this->messages["$field.$msg"] ?? $msg;
    }
}

class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation échouée');
    }
}
