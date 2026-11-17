<?php

require "conexao.php";

if (metodo() !== "POST") {
    erro("Use POST para criar a conta.", null, 405);
}

$dados = dadosRecebidos();
$nome = trim($dados["nome"] ?? "");
$email = trim($dados["email"] ?? "");
$senha = (string) ($dados["senha"] ?? "");

if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
    erro("Digite seu nome (pelo menos 3 letras).", "nomeCadastro");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    erro("Digite um e-mail válido.", "emailCadastro");
}
if (strlen($senha) < 6) {
    erro("A senha precisa ter pelo menos 6 caracteres.", "senhaCadastro");
}
if ($senha !== ($dados["confirmacao"] ?? "")) {
    erro("A confirmação não confere com a senha.", "confirmacaoCadastro");
}
if (consultar("SELECT id FROM usuarios WHERE email = ?", [$email])) {
    erro("Esse e-mail já tem conta.", "emailCadastro");
}

// O cadastro público sempre cria um usuário comum (cliente). O perfil é definido no servidor.
executar(
    "INSERT INTO usuarios (nome, email, senha, cargo, ativo, ativo_desde) VALUES (?, ?, ?, 'cliente', 1, ?)",
    [$nome, $email, password_hash($senha, PASSWORD_DEFAULT), hoje()]
);

iniciarSessaoUsuario(ultimoId());

responder(usuarioLogado(), 201);
