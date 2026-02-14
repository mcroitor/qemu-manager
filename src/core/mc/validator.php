<?php

namespace mc;

/**
 * Input data validation helper.
 */
class Validator
{
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->errors = [];
    }

    /**
        * Returns all validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
        * Checks whether validation has any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
        * Checks whether validation passed (no errors).
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
        * Returns first validation error.
     */
    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }

    /**
        * Adds validation error for field.
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[] = "{$field}: {$message}";
    }

    /**
        * Validates required field.
     */
    public function required(string $field, string $message = "Field is required"): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
            $this->addError($field, $message);
        }
        return $this;
    }

    /**
        * Validates minimum string length.
     */
    public function minLength(string $field, int $minLength, ?string $message = null): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            if (strlen($value) < $minLength) {
                $msg = $message ?? "Must be at least {$minLength} characters long";
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
        * Validates maximum string length.
     */
    public function maxLength(string $field, int $maxLength, ?string $message = null): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            if (strlen($value) > $maxLength) {
                $msg = $message ?? "Must be no more than {$maxLength} characters long";
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
        * Validates field against regular expression.
     */
    public function pattern(string $field, string $pattern, string $message = "Invalid format"): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            if (!preg_match($pattern, $value)) {
                $this->addError($field, $message);
            }
        }
        return $this;
    }

    /**
        * Validates numeric value.
     */
    public function numeric(string $field, string $message = "Must be a number"): self
    {
        if (isset($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $this->addError($field, $message);
            }
        }
        return $this;
    }

    /**
        * Validates integer value.
     */
    public function integer(string $field, string $message = "Must be an integer"): self
    {
        if (isset($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
                $this->addError($field, $message);
            }
        }
        return $this;
    }

    /**
        * Validates numeric range.
     */
    public function range(string $field, int $min, int $max, ?string $message = null): self
    {
        if (isset($this->data[$field])) {
            $value = (int)$this->data[$field];
            if ($value < $min || $value > $max) {
                $msg = $message ?? "Must be between {$min} and {$max}";
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
        * Validates email address.
     */
    public function email(string $field): self
    {
        if (isset($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, "Invalid email format");
            }
        }
        return $this;
    }

    /**
        * Validates IP address.
     */
    public function ip(string $field): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_IP)) {
                $this->addError($field, "Invalid IP address");
            }
        }
        return $this;
    }

    /**
        * Validates MAC address.
     */
    public function mac(string $field): self
    {
        if (isset($this->data[$field])) {
            $pattern = '/^[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}:[0-9A-Fa-f]{2}$/';
            if (!preg_match($pattern, $this->data[$field])) {
                $this->addError($field, "Invalid MAC address format");
            }
        }
        return $this;
    }

    /**
        * Validates URL.
     */
    public function url(string $field): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->addError($field, "Invalid URL format");
            }
        }
        return $this;
    }

    /**
        * Validates that value is in allowed list.
     */
    public function in(string $field, array $allowedValues, ?string $message = null): self
    {
        if (isset($this->data[$field])) {
            if (!in_array($this->data[$field], $allowedValues, true)) {
                $msg = $message;
                if ($msg === null) {
                    $allowed = implode(', ', $allowedValues);
                    $msg = "Must be one of: {$allowed}";
                }
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
     * Validates file name.
     */
    public function filename(string $field, string $message = "Invalid filename"): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            // Check for invalid file name characters
            if (preg_match('/[<>:"|?*\\\\\/]/', $value)) {
                $this->addError($field, $message);
                return $this;
            }
            // Check Windows reserved names
            $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
            if (in_array(strtoupper($value), $reserved)) {
                $this->addError($field, "Reserved filename");
            }
        }
        return $this;
    }

    /**
        * Validates file size in bytes.
     */
    public function fileSize(string $field, int $maxSize): self
    {
        if (isset($this->data[$field])) {
            $value = (int)$this->data[$field];
            if ($value > $maxSize) {
                $this->addError($field, "File size must not exceed " . util::size_bytes_to_readable($maxSize));
            }
        }
        return $this;
    }

    /**
     * Validates file path to prevent directory traversal.
     */
    public function safePath(string $field, string $message = "Invalid file path"): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            // Check directory escape attempts
            if (
                str_starts_with($value, './') ||
                str_starts_with($value, '.\\') ||
                strpos($value, '/../') !== false ||
                strpos($value, '\\..\\') !== false ||
                $value === '..' ||
                $value === '.'
            ) {
                $this->addError($field, $message);
                return $this;
            }
            // Check absolute paths
            if (strpos($value, '/') === 0 || preg_match('/^[a-zA-Z]:/', $value)) {
                $this->addError($field, "Relative paths only");
            }
        }
        return $this;
    }

    /**
        * Validates machine/container name (letters, numbers, hyphens, underscores).
     */
    public function machineName(string $field, string $message = "Invalid machine name. Use only letters, numbers, hyphens and underscores"): self
    {
        if (isset($this->data[$field])) {
            $pattern = '/^[a-zA-Z0-9_-]+$/';
            if (!preg_match($pattern, $this->data[$field])) {
                $this->addError($field, $message);
            }
        }
        return $this;
    }

    /**
     * Validates uniqueness in database.
     */
    public function unique(string $field, string $table, string $column, ?string $message = null, array $exclude = []): self
    {
        if (isset($this->data[$field])) {
            $conditions = [$column => $this->data[$field]];
            
            // Add exclusions for UPDATE operations
            foreach ($exclude as $excludeField => $excludeValue) {
                $conditions[] = "{$excludeField} != '{$excludeValue}'";
            }
            
            if (\config::$db->exists($table, $conditions)) {
                $msg = $message ?? "Value already exists";
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
        * Validates existence in database.
     */
    public function exists(string $field, string $table, string $column, ?string $message = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $conditions = [$column => $this->data[$field]];
            
            if (!\config::$db->exists($table, $conditions)) {
                $msg = $message ?? "Value does not exist";
                $this->addError($field, $msg);
            }
        }
        return $this;
    }

    /**
        * Runs custom validation callback.
     */
    public function custom(string $field, callable $callback, string $message = "Validation failed"): self
    {
        if (isset($this->data[$field])) {
            if (!$callback($this->data[$field])) {
                $this->addError($field, $message);
            }
        }
        return $this;
    }

    /**
        * Creates validator from POST data.
     */
    public static function fromPost(): self
    {
        $data = [];
        foreach ($_POST as $key => $value) {
            $data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        return new self($data);
    }

    /**
        * Creates validator from GET data.
     */
    public static function fromGet(): self
    {
        $data = [];
        foreach ($_GET as $key => $value) {
            $data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
        }
        return new self($data);
    }

    /**
        * Returns sanitized data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
        * Returns field value.
     */
    public function get(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }
}
