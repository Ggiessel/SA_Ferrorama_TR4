<?php
/*
  Conexão com o MySQL usando PDO.

  Sempre use "?" no lugar dos valores (evita SQL Injection):
    consultar("SELECT * FROM trens WHERE codigo = ?", [$codigo]);
*/

declare(strict_types=1);

/** Devolve a conexão (abre só uma vez por pedido). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORTA, DB_NOME);
        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // erro do banco vira exceção
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // linhas como array ['coluna' => valor]
            PDO::ATTR_EMULATE_PREPARES => false,              // números chegam como número
        ]);
    }
    return $pdo;
}

/** Roda um SELECT e devolve todas as linhas. */
function consultar(string $sql, array $valores = []): array
{
    $comando = db()->prepare($sql);
    $comando->execute($valores);
    $linhas = $comando->fetchAll();
    $comando->closeCursor();
    return $linhas;
}

/** Igual a consultar(), mas devolve só a primeira linha (ou null). */
function consultarUm(string $sql, array $valores = []): ?array
{
    $linhas = consultar($sql, $valores);
    return $linhas[0] ?? null;
}

/** Roda INSERT/UPDATE/DELETE/CALL. Devolve quantas linhas foram afetadas. */
function executar(string $sql, array $valores = []): int
{
    $comando = db()->prepare($sql);
    $comando->execute($valores);
    $afetadas = $comando->rowCount();
    $comando->closeCursor();
    return $afetadas;
}

/** id gerado pelo último INSERT. */
function ultimoId(): int
{
    return (int) db()->lastInsertId();
}

/** "?, ?, ?" para usar em "WHERE id IN (...)". */
function marcadores(array $lista): string
{
    return implode(', ', array_fill(0, count($lista), '?'));
}
