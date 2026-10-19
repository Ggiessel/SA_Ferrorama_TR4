<?php
/*
  Login: senhas, sessão e permissões.

  Como funciona:
  1. O site manda e-mail e senha para POST /auth/login.
  2. A API confere e guarda o id do usuário na SESSÃO do PHP.
  3. O navegador recebe um cookie de sessão e manda de volta sozinho
     em todo pedido. Assim a API sabe quem está logado.
*/

declare(strict_types=1);

function iniciarSessao(): void
{
    session_name('TR4SESSAO');
    session_set_cookie_params([
        'lifetime' => 0,          // dura até fechar o navegador
        'path' => '/',
        'httponly' => true,       // o JavaScript não consegue ler o cookie (mais seguro)
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- Senhas (mesmo cálculo do banco: SHA2(salt + senha, 256)) ---------- */

function gerarSalt(): string
{
    return bin2hex(random_bytes(16)); // 32 caracteres
}

function hashSenha(string $salt, string $senha): string
{
    return hash('sha256', $salt . $senha);
}

function senhaConfere(array $usuario, string $senha): bool
{
    // hash_equals compara sem "vazar" pelo tempo quantos caracteres acertaram
    return hash_equals($usuario['senha_hash'], hashSenha($usuario['senha_salt'], $senha));
}

/* ---------- Sessão ---------- */

function entrar(array $usuario): void
{
    session_regenerate_id(true); // novo id de sessão a cada login (evita sequestro de sessão)
    $_SESSION['usuario_id'] = (int) $usuario['id'];
}

function sair(): void
{
    $_SESSION = [];
    session_destroy();
}

/* ---------- Permissões ---------- */

/** Devolve o usuário logado ou dá erro 401. */
function exigirLogin(): array
{
    $id = $_SESSION['usuario_id'] ?? null;
    if (!$id) {
        throw new ErroApi(401, 'Entre na sua conta para continuar.');
    }

    $usuario = consultarUm('SELECT * FROM usuarios WHERE id = ?', [$id]);
    if (!$usuario) {
        sair();
        throw new ErroApi(401, 'Usuário não encontrado. Entre de novo.');
    }
    return $usuario;
}

/**
 * Só deixa passar quem tem um dos cargos. Devolve o usuário logado.
 *   $usuario = exigirCargo('gestor', 'maquinista');
 * Sem argumentos = qualquer cargo (mas precisa ter escolhido um).
 */
function exigirCargo(string ...$cargos): array
{
    $usuario = exigirLogin();
    if (!$usuario['cargo']) {
        throw new ErroApi(403, 'Escolha seu cargo antes de continuar.');
    }
    if ($cargos && !in_array($usuario['cargo'], $cargos, true)) {
        throw new ErroApi(403, 'Seu cargo não tem acesso a esta função.');
    }
    return $usuario;
}
