<?php

namespace mc;

/**
 * Класс для валидации входных данных
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
     * Получить все ошибки валидации
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Проверить, есть ли ошибки
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Проверить, валидны ли данные (нет ошибок)
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Получить первую ошибку
     */
    public function getFirstError(): string
    {
        return $this->errors[0] ?? '';
    }

    /**
     * Добавить ошибку
     */
    private function addError(string $field, string $message): void
    {
        $this->errors[] = "{$field}: {$message}";
    }

    /**
     * Валидация обязательного поля
     */
    public function required(string $field, string $message = "Field is required"): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
            $this->addError($field, $message);
        }
        return $this;
    }

    /**
     * Валидация минимальной длины строки
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
     * Валидация максимальной длины строки
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
     * Валидация регулярного выражения
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
     * Валидация числового значения
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
     * Валидация целого числа
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
     * Валидация диапазона чисел
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
     * Валидация email
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
     * Валидация IP адреса
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
     * Валидация MAC адреса
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
     * Валидация URL
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
     * Валидация значения из списка
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
     * Валидация имени файла
     */
    public function filename(string $field, string $message = "Invalid filename"): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            // Проверяем на недопустимые символы в имени файла
            if (preg_match('/[<>:"|?*\\\\\/]/', $value)) {
                $this->addError($field, $message);
                return $this;
            }
            // Проверяем на зарезервированные имена Windows
            $reserved = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
            if (in_array(strtoupper($value), $reserved)) {
                $this->addError($field, "Reserved filename");
            }
        }
        return $this;
    }

    /**
     * Валидация размера файла (в байтах)
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
     * Валидация пути к файлу (защита от directory traversal)
     */
    public function safePath(string $field, string $message = "Invalid file path"): self
    {
        if (isset($this->data[$field])) {
            $value = (string)$this->data[$field];
            // Проверяем на попытки выхода из директории
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
            // Проверяем на абсолютные пути
            if (strpos($value, '/') === 0 || preg_match('/^[a-zA-Z]:/', $value)) {
                $this->addError($field, "Relative paths only");
            }
        }
        return $this;
    }

    /**
     * Валидация имени машины/контейнера (только буквы, цифры, тире, подчеркивания)
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
     * Валидация уникальности в базе данных
     */
    public function unique(string $field, string $table, string $column, ?string $message = null, array $exclude = []): self
    {
        if (isset($this->data[$field])) {
            $conditions = [$column => $this->data[$field]];
            
            // Добавляем исключения для UPDATE операций
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
     * Валидация существования в базе данных
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
     * Пользовательская валидация
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
     * Статический метод для создания валидатора из POST данных
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
     * Статический метод для создания валидатора из GET данных
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
     * Получить очищенные данные
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Получить значение поля
     */
    public function get(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }
}
