<?php
/**
 * Classe de Utilitários de Data
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

class Date
{
    /**
     * Obter data atual no formato brasileiro
     */
    public static function now(string $format = 'd/m/Y H:i:s'): string
    {
        return date($format);
    }
    
    /**
     * Obter data atual no formato do banco
     */
    public static function nowDb(): string
    {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Obter apenas a data atual
     */
    public static function today(string $format = 'Y-m-d'): string
    {
        return date($format);
    }
    
    /**
     * Converter data para formato brasileiro
     */
    public static function toBrazilian(string $date): string
    {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return $date;
        }
        
        return date('d/m/Y', $timestamp);
    }
    
    /**
     * Converter data e hora para formato brasileiro
     */
    public static function toBrazilianDateTime(string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }
        
        $timestamp = strtotime($datetime);
        
        if ($timestamp === false) {
            return $datetime;
        }
        
        return date('d/m/Y H:i:s', $timestamp);
    }
    
    /**
     * Converter data brasileira para formato do banco
     */
    public static function toDatabase(string $date): string
    {
        if (empty($date)) {
            return '';
        }
        
        // Se já estiver no formato correto
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Tentar converter formato brasileiro
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }
        
        $timestamp = strtotime($date);
        
        if ($timestamp === false) {
            return '';
        }
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Calcular diferença em minutos
     */
    public static function diffInMinutes(string $start, string $end = null): int
    {
        $end = $end ?: self::nowDb();
        
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        
        if ($startTime === false || $endTime === false) {
            return 0;
        }
        
        return intval(($endTime - $startTime) / 60);
    }
    
    /**
     * Calcular diferença em horas
     */
    public static function diffInHours(string $start, string $end = null): float
    {
        return round(self::diffInMinutes($start, $end) / 60, 2);
    }
    
    /**
     * Formatar duração em minutos para texto legível
     */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $remainingMinutes . 'min';
    }
    
    /**
     * Verificar se é data válida
     */
    public static function isValid(string $date, string $format = 'Y-m-d'): bool
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }
    
    /**
     * Obter início do dia
     */
    public static function startOfDay(string $date = null): string
    {
        $date = $date ?: self::today();
        return $date . ' 00:00:00';
    }
    
    /**
     * Obter fim do dia
     */
    public static function endOfDay(string $date = null): string
    {
        $date = $date ?: self::today();
        return $date . ' 23:59:59';
    }
    
    /**
     * Obter nome do dia da semana
     */
    public static function dayOfWeek(string $date = null): string
    {
        $timestamp = $date ? strtotime($date) : time();
        
        $days = [
            'Sunday' => 'Domingo',
            'Monday' => 'Segunda-feira',
            'Tuesday' => 'Terça-feira',
            'Wednesday' => 'Quarta-feira',
            'Thursday' => 'Quinta-feira',
            'Friday' => 'Sexta-feira',
            'Saturday' => 'Sábado'
        ];
        
        $englishDay = date('l', $timestamp);
        return $days[$englishDay] ?? $englishDay;
    }
    
    /**
     * Obter nome do mês
     */
    public static function monthName(string $date = null): string
    {
        $timestamp = $date ? strtotime($date) : time();
        
        $months = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril', '05' => 'Maio', '06' => 'Junho',
            '07' => 'Julho', '08' => 'Agosto', '09' => 'Setembro',
            '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
        ];
        
        $monthNumber = date('m', $timestamp);
        return $months[$monthNumber] ?? '';
    }
    
    /**
     * Verificar se é hoje
     */
    public static function isToday(string $date): bool
    {
        return date('Y-m-d', strtotime($date)) === self::today();
    }
    
    /**
     * Verificar se é ontem
     */
    public static function isYesterday(string $date): bool
    {
        return date('Y-m-d', strtotime($date)) === date('Y-m-d', strtotime('-1 day'));
    }
    
    /**
     * Obter data relativa (há X minutos/horas/dias)
     */
    public static function relative(string $date): string
    {
        $timestamp = strtotime($date);
        $now = time();
        $diff = $now - $timestamp;
        
        if ($diff < 60) {
            return 'agora';
        }
        
        if ($diff < 3600) {
            $minutes = intval($diff / 60);
            return "há $minutes min";
        }
        
        if ($diff < 86400) {
            $hours = intval($diff / 3600);
            return "há $hours h";
        }
        
        if (self::isYesterday($date)) {
            return 'ontem às ' . date('H:i', $timestamp);
        }
        
        if ($diff < 604800) { // 7 dias
            $days = intval($diff / 86400);
            return "há $days dias";
        }
        
        return self::toBrazilianDateTime($date);
    }
    
    /**
     * Obter timestamp atual
     */
    public static function timestamp(): int
    {
        return time();
    }
    
    /**
     * Converter timestamp para data
     */
    public static function fromTimestamp(int $timestamp, string $format = 'd/m/Y H:i:s'): string
    {
        return date($format, $timestamp);
    }
    
    /**
     * Adicionar tempo a uma data
     */
    public static function addTime(string $date, string $interval): string
    {
        $timestamp = strtotime($date . ' ' . $interval);
        
        if ($timestamp === false) {
            return $date;
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Subtrair tempo de uma data
     */
    public static function subTime(string $date, string $interval): string
    {
        $timestamp = strtotime($date . ' -' . $interval);
        
        if ($timestamp === false) {
            return $date;
        }
        
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Obter período do dia (manhã, tarde, noite)
     */
    public static function periodOfDay(string $time = null): string
    {
        $hour = $time ? intval(date('H', strtotime($time))) : intval(date('H'));
        
        if ($hour >= 6 && $hour < 12) {
            return 'manhã';
        } elseif ($hour >= 12 && $hour < 18) {
            return 'tarde';
        } else {
            return 'noite';
        }
    }
}