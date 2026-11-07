<?php

require "conexao.php";

$dados = dadosRecebidos();
$email = trim($dados["email"] ?? "");
$senha = $dados["senha"] ?? "";

if ($email === "") {
    erro("Digite seu e-mail.", "emailLogin");
}
if ($senha === "") {
    erro("Digite sua senha.", "senhaLogin");
}

$resultado = consultar("SELECT * FROM usuarios WHERE email = ?", [$email]);

if (!$resultado) {
    erro("Não existe conta com esse e-mail.", "emailLogin", 401);
}

$usuario = $resultado[0];

if (!password_verify($senha, $usuario["senha"])) {
    erro("Senha incorreta.", "senhaLogin", 401);
}

unset($usuario["senha"]);
$_SESSION["usuario"] = $usuario;

responder($usuario);
