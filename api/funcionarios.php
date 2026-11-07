<?php

require "conexao.php";

exigirCargo("gestor");

$id = $_GET["id"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $dados = dadosRecebidos();
    $campos = ["cargo", "nome", "ativo_desde", "email", "telefone", "cpf", "nascimento", "endereco"];

    foreach ($campos as $campo) {
        $dados[$campo] = trim($dados[$campo] ?? "");
    }

    if ($dados["cargo"] === "") {
        erro("Informe o cargo.", "cargo");
    }
    if (strlen($dados["nome"]) < 3) {
        erro("Informe o nome completo.", "nome");
    }
    if ($dados["ativo_desde"] === "") {
        erro("Informe desde quando o funcionário está ativo.", "ativo_desde");
    }
    if (!filter_var($dados["email"], FILTER_VALIDATE_EMAIL)) {
        erro("Informe um e-mail válido.", "email");
    }
    if (consultar("SELECT id FROM funcionarios WHERE email = ? AND id <> ?", [$dados["email"], $id])) {
        erro("Esse e-mail já é de outro funcionário.", "email");
    }
    if ($dados["cpf"] !== "" && consultar("SELECT id FROM funcionarios WHERE cpf = ? AND id <> ?", [$dados["cpf"], $id])) {
        erro("Esse CPF já é de outro funcionário.", "cpf");
    }

    executar(
        "UPDATE funcionarios SET cargo = ?, nome = ?, ativo_desde = ?, email = ?, telefone = ?, cpf = ?, nascimento = ?, endereco = ? WHERE id = ?",
        [
            $dados["cargo"],
            $dados["nome"],
            $dados["ativo_desde"],
            $dados["email"],
            $dados["telefone"] ?: null,
            $dados["cpf"] ?: null,
            $dados["nascimento"] ?: null,
            $dados["endereco"] ?: null,
            $id
        ]
    );

    responder(["ok" => true]);
}

if ($id) {
    $funcionario = consultar("SELECT * FROM funcionarios WHERE id = ?", [$id]);

    if (!$funcionario) {
        erro("Funcionário não encontrado.", null, 404);
    }

    responder($funcionario[0]);
}

$busca = "%" . ($_GET["busca"] ?? "") . "%";

responder(consultar("SELECT * FROM funcionarios WHERE nome LIKE ? OR cargo LIKE ? ORDER BY nome", [$busca, $busca]));
