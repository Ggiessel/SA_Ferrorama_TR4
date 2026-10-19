<?php
/*
============================================================
  FERRORAMA TR4 — API EM PHP
  Arquivo: api/index.php
============================================================

  Todos os pedidos do site chegam aqui. O endereço depois de
  "index.php" diz qual endpoint rodar:

    GET  /api/index.php/rotas      -> lista de rotas
    POST /api/index.php/auth/login -> login

  Teste rápido no navegador (com Apache e MySQL ligados no XAMPP):
    http://localhost/SA_Ferrorama_TR4/api/index.php/saude
*/

declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/src/funcoes.php';
require __DIR__ . '/src/banco.php';
require __DIR__ . '/src/autenticacao.php';

// Carrega todos os endpoints (cada arquivo registra os seus com rota())
foreach (glob(__DIR__ . '/src/endpoints/*.php') as $arquivo) {
    require $arquivo;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Avisos do PHP não podem sair no meio do JSON: viram exceção e caem no catch
ini_set('display_errors', '0');
set_error_handler(function (int $nivel, string $mensagem, string $arquivo, int $linha) {
    throw new ErrorException($mensagem, 0, $nivel, $arquivo, $linha);
});

try {
    iniciarSessao();
    despachar();
} catch (ErroApi $erro) {
    responder(['erro' => $erro->getMessage(), 'campo' => $erro->campo], $erro->status);
} catch (PDOException $erro) {
    // Erro de comando: o código do MySQL vem em errorInfo[1]. Erro de conexão: vem em getCode().
    $codigo = isset($erro->errorInfo[1]) ? (int) $erro->errorInfo[1] : (int) $erro->getCode();

    // Não conectou: MySQL desligado (2002), usuário/senha (1045) ou banco não existe (1049)
    if (in_array($codigo, [2002, 1045, 1049], true)) {
        responder(['erro' => 'O banco de dados não está disponível. Confira se o MySQL está ligado no XAMPP, '
            . 'se o script banco-de-dados/ferrorama_tr4.sql foi importado e os dados de api/config.php.'
            . (MODO_DESENVOLVIMENTO ? ' Detalhe: ' . $erro->getMessage() : '')], 503);
    }
    if ($codigo === 1062) {
        responder(['erro' => 'Esse registro já existe.'], 409);
    }
    if (in_array($codigo, [3819, 4025], true)) {
        responder(['erro' => 'Algum valor enviado não é permitido pelo banco.'], 400);
    }
    responder(['erro' => 'Erro no banco de dados.'
        . (MODO_DESENVOLVIMENTO ? ' Detalhe: ' . $erro->getMessage() : '')], 500);
} catch (Throwable $erro) {
    responder(['erro' => 'Erro interno no servidor.'
        . (MODO_DESENVOLVIMENTO ? ' Detalhe: ' . $erro->getMessage() . ' em ' . basename($erro->getFile()) . ':' . $erro->getLine() : '')], 500);
}
