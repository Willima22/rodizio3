<?php
/**
 * Classe de Sanitização de Dados
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

class Sanitizer
{
    /**
     * Sanitizar entrada geral
     */
    public static function input(?string $input, int $maxLength = null): string
    {
        if ($input === null) {
            return '';
        }
        
        // Remover tags HTML
        $input = strip_tags($input);
        
        // Normalizar espaços
        $input = preg_replace('/\s+/', ' ', $input);
        
        // Trim
        $input = trim($input);
        
        // Limitar tamanho
        if ($maxLength && strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    /**
     * Sanitizar nome
     */
    public static function name(?string $name): string
    {
        $name = self::input($name, 255);
        
        // Remover caracteres especiais, manter acentos
        $name = preg_replace('/[^a-zA-ZÀ-ÿ\s\-\']/', '', $name);
        
        // Capitalizar cada palavra
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        
        return $name;
    }
    
    /**
     * Sanitizar telefone
     */
    public static function phone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }
        
        // Remover tudo exceto números, parênteses, hífen, espaço e +
        $phone = preg_replace('/[^0-9()\-\s+]/', '', $phone);
        
        // Normalizar espaços
        $phone = preg_replace('/\s+/', ' ', trim($phone));
        
        // Limitar tamanho
        return substr($phone, 0, 20);
    }
    
    /**
     * Sanitizar email
     */
    public static function email(?string $email): string
    {
        if ($email === null) {
            return '';
        }
        
        // Remover espaços e converter para minúsculas
        $email = strtolower(trim($email));
        
        // Validar e sanitizar
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        return $email ?: '';
    }
    
    /**
     * Sanitizar texto longo (observações)
     */
    public static function text(?string $text, int $maxLength = 1000): string
    {
        if ($text === null) {
            return '';
        }
        
        // Remover tags HTML mantendo quebras de linha
        $text = strip_tags($text);
        
        // Normalizar quebras de linha
        $text = preg_replace('/\r\n|\r|\n/', "\n", $text);
        
        // Remover múltiplas quebras de linha
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Normalizar espaços em cada linha
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);
        $text = implode("\n", $lines);
        
        // Limitar tamanho
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }
        
        return trim($text);
    }
    
    /**
     * Sanitizar valor monetário
     */
    public static function money(?string $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        
        // Remover tudo exceto números, vírgula e ponto
        $value = preg_replace('/[^0-9,.]/', '', $value);
        
        // Converter vírgula para ponto
        $value = str_replace(',', '.', $value);
        
        // Converter para float
        $value = floatval($value);
        
        // Limitar a 2 casas decimais
        return round($value, 2);
    }
    
    /**
     * Sanitizar número inteiro
     */
    public static function int(?string $value): int
    {
        if ($value === null) {
            return 0;
        }
        
        // Remover tudo exceto números e sinal negativo
        $value = preg_replace('/[^0-9\-]/', '', $value);
        
        return intval($value);
    }
    
    /**
     * Sanitizar UUID
     */
    public static function uuid(?string $uuid): string
    {
        if ($uuid === null) {
            return '';
        }
        
        // Remover tudo exceto caracteres válidos para UUID
        $uuid = preg_replace('/[^a-f0-9\-]/', '', strtolower($uuid));
        
        // Verificar formato básico
        if (strlen($uuid) !== 36) {
            return '';
        }
        
        return $uuid;
    }
    
    /**
     * Sanitizar NFC UID
     */
    public static function nfcUid(?string $uid): string
    {
        if ($uid === null) {
            return '';
        }
        
        // Remover espaços e converter para maiúsculas
        $uid = strtoupper(trim($uid));
        
        // Remover tudo exceto caracteres hexadecimais
        $uid = preg_replace('/[^A-F0-9]/', '', $uid);
        
        // Limitar tamanho (UIDs NFC geralmente têm 4-10 bytes = 8-20 chars hex)
        return substr($uid, 0, 20);
    }
    
    /**
     * Sanitizar data
     */
    public static function date(?string $date): string
    {
        if ($date === null) {
            return '';
        }
        
        // Tentar converter para formato padrão
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Sanitizar hora
     */
    public static function time(?string $time): string
    {
        if ($time === null) {
            return '';
        }
        
        // Remover tudo exceto números e dois pontos
        $time = preg_replace('/[^0-9:]/', '', $time);
        
        // Verificar formato básico HH:MM
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return '';
        }
        
        // Validar hora
        $parts = explode(':', $time);
        $hour = intval($parts[0]);
        $minute = intval($parts[1]);
        
        if ($hour > 23 || $minute > 59) {
            return '';
        }
        
        return sprintf('%02d:%02d', $hour, $minute);
    }
    
    /**
     * Sanitizar array de dados
     */
    public static function array(array $data, array $rules): array
    {
        $sanitized = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            switch ($rule) {
                case 'name':
                    $sanitized[$field] = self::name($value);
                    break;
                case 'email':
                    $sanitized[$field] = self::email($value);
                    break;
                case 'phone':
                    $sanitized[$field] = self::phone($value);
                    break;
                case 'text':
                    $sanitized[$field] = self::text($value);
                    break;
                case 'money':
                    $sanitized[$field] = self::money($value);
                    break;
                case 'int':
                    $sanitized[$field] = self::int($value);
                    break;
                case 'uuid':
                    $sanitized[$field] = self::uuid($value);
                    break;
                case 'date':
                    $sanitized[$field] = self::date($value);
                    break;
                case 'time':
                    $sanitized[$field] = self::time($value);
                    break;
                default:
                    $sanitized[$field] = self::input($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Escapar para output HTML
     */
    public static function output(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Escapar para atributo HTML
     */
    public static function attribute(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitizar filename
     */
    public static function filename(?string $filename): string
    {
        if ($filename === null) {
            return '';
        }
        
        // Remover caracteres perigosos
        $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
        
        // Limitar tamanho
        return substr($filename, 0, 255);
    }
}