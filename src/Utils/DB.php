<?php
/**
 * Classe de Conexão com Banco de Dados
 * Sistema de Gerenciamento de Salão - Fast Escova
 */

namespace Utils;

use PDO;
use PDOException;
use Exception;

class DB
{
    private static $instance = null;
    private $connection = null;
    private $transactionLevel = 0;

    /**
     * Construtor privado para Singleton
     */
    private function __construct()
    {
        try {
            $this->connection = new PDO(
                DB_DSN,
                DB_USER,
                DB_PASS,
                DB_OPTIONS
            );
        } catch (PDOException $e) {
            error_log("Erro de conexão com banco: " . $e->getMessage());
            throw new Exception("Erro de conexão com banco de dados");
        }
    }

    /**
     * Obter instância singleton
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obter conexão PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Executar query preparada
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erro na query: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Erro na consulta ao banco de dados");
        }
    }

    /**
     * Buscar um registro
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Buscar múltiplos registros
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Executar INSERT e retornar último ID
     */
    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Executar UPDATE/DELETE e retornar linhas afetadas
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Iniciar transação
     */
    public function beginTransaction(): bool
    {
        if ($this->transactionLevel === 0) {
            $result = $this->connection->beginTransaction();
        } else {
            $result = true;
        }
        $this->transactionLevel++;
        return $result;
    }

    /**
     * Confirmar transação
     */
    public function commit(): bool
    {
        if ($this->transactionLevel === 1) {
            $result = $this->connection->commit();
        } else {
            $result = true;
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
        return $result;
    }

    /**
     * Desfazer transação
     */
    public function rollback(): bool
    {
        if ($this->transactionLevel === 1) {
            $result = $this->connection->rollback();
        } else {
            $result = true;
        }
        $this->transactionLevel = max(0, $this->transactionLevel - 1);
        return $result;
    }

    /**
     * Verificar se está em transação
     */
    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    /**
     * Escapar string para LIKE
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    /**
     * Gerar UUID
     */
    public function generateUuid(): string
    {
        $stmt = $this->query("SELECT UUID() as uuid");
        $result = $stmt->fetch();
        return $result['uuid'];
    }

    /**
     * Verificar se tabela existe
     */
    public function tableExists(string $table): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        $result = $this->fetchOne($sql, ['table' => $table]);
        return $result !== null;
    }

    /**
     * Obter informações da tabela
     */
    public function getTableInfo(string $table): array
    {
        $sql = "DESCRIBE `$table`";
        return $this->fetchAll($sql);
    }

    /**
     * Destrutor - fechar conexão
     */
    public function __destruct()
    {
        $this->connection = null;
    }

    /**
     * Prevenir clonagem
     */
    private function __clone() {}

    /**
     * Prevenir unserialize
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}