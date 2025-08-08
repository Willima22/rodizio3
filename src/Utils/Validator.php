<?php
/**
 * Classe de Validação de Dados
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

class Validator
{
    private array $errors = [];
    private array $data = [];
    
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }
    
    /**
     * Validar campo obrigatório
     */
    public function required(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        
        if (empty($value) && $value !== '0') {
            $this->errors[$field][] = $message ?: "O campo $field é obrigatório";
        }
        
        return $this;
    }
    
    /**
     * Validar tamanho mínimo
     */
    public function min(string $field, int $min, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (strlen($value) < $min) {
            $this->errors[$field][] = $message ?: "O campo $field deve ter pelo menos $min caracteres";
        }
        
        return $this;
    }
    
    /**
     * Validar tamanho máximo
     */
    public function max(string $field, int $max, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (strlen($value) > $max) {
            $this->errors[$field][] = $message ?: "O campo $field deve ter no máximo $max caracteres";
        }
        
        return $this;
    }
    
    /**
     * Validar email
     */
    public function email(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?: "O campo $field deve ser um email válido";
        }
        
        return $this;
    }
    
    /**
     * Validar telefone brasileiro
     */
    public function phone(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            // Remove tudo exceto números
            $numbers = preg_replace('/[^0-9]/', '', $value);
            
            // Telefone brasileiro deve ter 10 ou 11 dígitos
            if (strlen($numbers) < 10 || strlen($numbers) > 11) {
                $this->errors[$field][] = $message ?: "O campo $field deve ser um telefone válido";
            }
        }
        
        return $this;
    }
    
    /**
     * Validar valor numérico
     */
    public function numeric(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field][] = $message ?: "O campo $field deve ser um número";
        }
        
        return $this;
    }
    
    /**
     * Validar valor inteiro
     */
    public function integer(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->errors[$field][] = $message ?: "O campo $field deve ser um número inteiro";
        }
        
        return $this;
    }
    
    /**
     * Validar valor mínimo
     */
    public function minValue(string $field, $min, string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        
        if ($value !== null && $value < $min) {
            $this->errors[$field][] = $message ?: "O campo $field deve ser pelo menos $min";
        }
        
        return $this;
    }
    
    /**
     * Validar valor máximo
     */
    public function maxValue(string $field, $max, string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        
        if ($value !== null && $value > $max) {
            $this->errors[$field][] = $message ?: "O campo $field deve ser no máximo $max";
        }
        
        return $this;
    }
    
    /**
     * Validar data
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            $dateTime = \DateTime::createFromFormat($format, $value);
            
            if (!$dateTime || $dateTime->format($format) !== $value) {
                $this->errors[$field][] = $message ?: "O campo $field deve ser uma data válida ($format)";
            }
        }
        
        return $this;
    }
    
    /**
     * Validar UUID
     */
    public function uuid(string $field, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            
            if (!preg_match($pattern, $value)) {
                $this->errors[$field][] = $message ?: "O campo $field deve ser um UUID válido";
            }
        }
        
        return $this;
    }
    
    /**
     * Validar se valor está em lista
     */
    public function in(string $field, array $values, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !in_array($value, $values, true)) {
            $valuesList = implode(', ', $values);
            $this->errors[$field][] = $message ?: "O campo $field deve ser um dos valores: $valuesList";
        }
        
        return $this;
    }
    
    /**
     * Validar regex personalizado
     */
    public function regex(string $field, string $pattern, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value) && !preg_match($pattern, $value)) {
            $this->errors[$field][] = $message ?: "O campo $field tem formato inválido";
        }
        
        return $this;
    }
    
    /**
     * Validar se existe no banco
     */
    public function exists(string $field, string $table, string $column = 'id', string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        
        if (!empty($value)) {
            $db = DB::getInstance();
            $sql = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = :value";
            $result = $db->fetchOne($sql, ['value' => $value]);
            
            if (!$result || $result['count'] == 0) {
                $this->errors[$field][] = $message ?: "O valor do campo $field não existe";
            }
        }
        
        return $this;
    }
    
    /**
     * Validar se é único no banco
     */
    public function unique(string $field, string $table, string $column = null, string $exceptId = null, string $message = null): self
    {
        $value = $this->data[$field] ?? '';
        $column = $column ?: $field;
        
        if (!empty($value)) {
            $db = DB::getInstance();
            $sql = "SELECT COUNT(*) as count FROM `$table` WHERE `$column` = :value";
            $params = ['value' => $value];
            
            if ($exceptId) {
                $sql .= " AND id != :except_id";
                $params['except_id'] = $exceptId;
            }
            
            $result = $db->fetchOne($sql, $params);
            
            if ($result && $result['count'] > 0) {
                $this->errors[$field][] = $message ?: "O valor do campo $field já está em uso";
            }
        }
        
        return $this;
    }
    
    /**
     * Validação customizada
     */
    public function custom(string $field, callable $callback, string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        
        if (!$callback($value)) {
            $this->errors[$field][] = $message ?: "O campo $field é inválido";
        }
        
        return $this;
    }
    
    /**
     * Verificar se passou na validação
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }
    
    /**
     * Obter erros
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Obter primeiro erro de um campo
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
    
    /**
     * Obter todos os erros em uma string
     */
    public function getErrorsAsString(): string
    {
        $allErrors = [];
        
        foreach ($this->errors as $fieldErrors) {
            $allErrors = array_merge($allErrors, $fieldErrors);
        }
        
        return implode('. ', $allErrors);
    }
    
    /**
     * Adicionar erro manual
     */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field][] = $message;
        return $this;
    }
    
    /**
     * Validação estática rápida
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $validator = new self($data);
            
            foreach ($fieldRules as $rule => $params) {
                if (is_numeric($rule)) {
                    // Regra simples sem parâmetros
                    $rule = $params;
                    $params = [];
                } elseif (!is_array($params)) {
                    // Parâmetro único
                    $params = [$params];
                }
                
                switch ($rule) {
                    case 'required':
                        $validator->required($field, $params[0] ?? null);
                        break;
                    case 'email':
                        $validator->email($field, $params[0] ?? null);
                        break;
                    case 'min':
                        $validator->min($field, $params[0], $params[1] ?? null);
                        break;
                    case 'max':
                        $validator->max($field, $params[0], $params[1] ?? null);
                        break;
                    case 'numeric':
                        $validator->numeric($field, $params[0] ?? null);
                        break;
                    case 'integer':
                        $validator->integer($field, $params[0] ?? null);
                        break;
                }
            }
            
            $fieldErrors = $validator->getErrors();
            if (isset($fieldErrors[$field])) {
                $errors[$field] = $fieldErrors[$field];
            }
        }
        
        return $errors;
    }
}