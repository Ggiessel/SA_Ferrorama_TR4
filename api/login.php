<?php

require "conexao.php";

if (metodo() !== "POST") {
    erro("Use POST para entrar.", null, 405);
}

$dados = dadosRecebidos();
$email = trim($dados["email"] ?? "");
$senha = (string) ($dados["senha"] ?? "");

if ($email === "") {
    erro("Digite seu e-mail.", "emailLogin");
}
if ($senha === "") {
    erro("Digite sua senha.", "senhaLogin");
}

$registro = consultarUm("SELECT id, senha, ativo FROM usuarios WHERE email = ?", [$email]);

// Mesma mensagem para e-mail inexistente e senha errada: não revela quais e-mails têm conta.
if (!$registro || !password_verify($senha, $registro["senha"])) {
    erro("E-mail ou senha incorretos.", null, 401);
}

// Conta desativada: só informada depois de a senha conferir.
if (!(int) $registro["ativo"]) {
    erro("Sua conta está desativada. Procure o gestor do sistema.", null, 403);
}

if (password_needs_rehash($registro["senha"], PASSWORD_DEFAULT)) {
    executar("UPDATE usuarios SET senha = ? WHERE id = ?", [password_hash($senha, PASSWORD_DEFAULT), $registro["id"]]);
}

iniciarSessaoUsuario($registro["id"]);

responder(usuarioLogado());
