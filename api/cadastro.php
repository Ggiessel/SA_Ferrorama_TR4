<?php

require "conexao.php";

$dados = dadosRecebidos();
$nome = trim($dados["nome"] ?? "");
$email = trim($dados["email"] ?? "");
$senha = $dados["senha"] ?? "";

if (strlen($nome) < 3) {
    erro("Digite seu nome (pelo menos 3 letras).", "nomeCadastro");
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    erro("Digite um e-mail válido.", "emailCadastro");
}
if (strlen($senha) < 6) {
    erro("A senha precisa ter pelo menos 6 caracteres.", "senhaCadastro");
}
if (consultar("SELECT id FROM usuarios WHERE email = ?", [$email])) {
    erro("Esse e-mail já tem conta.", "emailCadastro");
}

executar(
    "INSERT INTO usuarios (nome, email, senha, cargo, ativo_desde) VALUES (?, ?, ?, ?, ?)",
    [$nome, $email, password_hash($senha, PASSWORD_DEFAULT), "cliente", hoje()]
);

$usuario = consultar("SELECT id, nome, email, cargo, nascimento, ativo_desde, telefone FROM usuarios WHERE email = ?", [$email])[0];
$_SESSION["usuario"] = $usuario;

responder($usuario, 201);
