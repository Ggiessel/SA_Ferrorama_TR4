<?php

require "conexao.php";

$usuario = usuarioLogado();

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $dados = dadosRecebidos();

    if (isset($dados["cargo"])) {
        if (!in_array($dados["cargo"], ["gestor", "maquinista", "cliente"])) {
            erro("Cargo inválido.");
        }
        executar("UPDATE usuarios SET cargo = ? WHERE id = ?", [$dados["cargo"], $usuario["id"]]);
    } else {
        $nome = trim($dados["nome"] ?? "");
        $email = trim($dados["email"] ?? "");

        if (strlen($nome) < 3) {
            erro("Informe seu nome completo.", "nome");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            erro("Informe um e-mail válido.", "email");
        }
        if (consultar("SELECT id FROM usuarios WHERE email = ? AND id <> ?", [$email, $usuario["id"]])) {
            erro("Esse e-mail já é usado por outra conta.", "email");
        }

        executar(
            "UPDATE usuarios SET nome = ?, email = ?, nascimento = ?, telefone = ? WHERE id = ?",
            [$nome, $email, ($dados["nascimento"] ?? "") ?: null, ($dados["telefone"] ?? "") ?: null, $usuario["id"]]
        );
    }
}

$usuario = consultar("SELECT id, nome, email, cargo, nascimento, ativo_desde, telefone FROM usuarios WHERE id = ?", [$usuario["id"]])[0];
$_SESSION["usuario"] = $usuario;

responder($usuario);
