<?php
declare(strict_types=1);

namespace SDSVGBook;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private array $config;
    private ?PDO $connection = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConnection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        foreach (['host', 'port', 'database', 'username', 'password', 'charset'] as $key) {
            if (!array_key_exists($key, $this->config)) {
                throw new RuntimeException(sprintf('Missing database configuration value: %s', $key));
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $this->config['host'],
            (int) $this->config['port'],
            (string) $this->config['database'],
            (string) $this->config['charset']
        );

        try {
            $this->connection = new PDO(
                $dsn,
                (string) $this->config['username'],
                (string) $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Failed to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->connection;
    }
}
