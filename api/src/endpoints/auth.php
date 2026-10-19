<?php
/*
  /auth — login, cadastro, "quem sou eu" e sair
  Usado por: login.html, signin.html e por todas as páginas (para checar a sessão).
*/

declare(strict_types=1);

// GET /saude -> testa se a API e o banco estão funcionando
rota('GET', '/saude', function () {
    consultar('SELECT 1');
    responder(['api' => 'ok', 'banco' => 'ok']);
});

// POST /auth/login   { email, senha }
rota('POST', '/auth/login', function () {
    $dados = corpo();
    $email = strtolower(texto($dados, 'email'));
    $senha = (string) ($dados['senha'] ?? '');

    if (!validarEmail($email)) {
        throw new ErroApi(400, 'Digite um e-mail válido, como nome@dominio.com.', 'emailLogin');
    }
    if ($senha === '') {
        throw new ErroApi(400, 'Digite sua senha.', 'senhaLogin');
    }

    $usuario = consultarUm('SELECT * FROM usuarios WHERE email = ?', [$email]);
    if (!$usuario) {
        throw new ErroApi(401, 'Não existe conta com esse e-mail. Clique em "Cadastre-se" para criar uma.', 'emailLogin');
    }
    if (!senhaConfere($usuario, $senha)) {
        throw new ErroApi(401, 'Senha incorreta. Tente de novo.', 'senhaLogin');
    }

    entrar($usuario);
    responder(['usuario' => usuarioPublico($usuario)]);
});

// POST /auth/cadastro   { nome, email, senha }
rota('POST', '/auth/cadastro', function () {
    $dados = corpo();
    $nome = preg_replace('/\s+/', ' ', texto($dados, 'nome'));
    $email = strtolower(texto($dados, 'email'));
    $senha = (string) ($dados['senha'] ?? '');

    if (mb_strlen($nome) < 3) {
        throw new ErroApi(400, 'Digite seu nome (pelo menos 3 letras).', 'nomeCadastro');
    }
    if (!validarEmail($email)) {
        throw new ErroApi(400, 'Digite um e-mail válido, como nome@dominio.com.', 'emailCadastro');
    }
    if (mb_strlen($senha) < 6) {
        throw new ErroApi(400, 'A senha precisa ter pelo menos 6 caracteres.', 'senhaCadastro');
    }
    if (consultarUm('SELECT id FROM usuarios WHERE email = ?', [$email])) {
        throw new ErroApi(409, 'Esse e-mail já tem conta. Volte e clique em "Entrar".', 'emailCadastro');
    }

    $salt = gerarSalt();
    executar(
        'INSERT INTO usuarios (nome, email, senha_salt, senha_hash, cargo, ativo_desde)
         VALUES (?, ?, ?, ?, NULL, ?)',
        [$nome, $email, $salt, hashSenha($salt, $senha), hojeISO()]
    );

    $usuario = consultarUm('SELECT * FROM usuarios WHERE id = ?', [ultimoId()]);
    entrar($usuario);
    responder(['usuario' => usuarioPublico($usuario)], 201);
});

// GET /auth/eu   -> dados de quem está logado
rota('GET', '/auth/eu', function () {
    responder(usuarioPublico(exigirLogin()));
});

// POST /auth/sair
rota('POST', '/auth/sair', function () {
    sair();
    responder(null, 204);
});
