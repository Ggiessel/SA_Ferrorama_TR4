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

if (!$resultado || !password_verify($senha, $resultado[0]["senha"])) {
    erro("E-mail ou senha incorretos.", null, 401);
}

$usuario = $resultado[0];

unset($usuario["senha"]);
$_SESSION["usuario"] = $usuario;

responder($usuario);
