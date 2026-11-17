<?php

/* Dados da própria conta: consulta, edição e troca de senha. */

require "conexao.php";

$usuario = usuarioLogado();
$acao = $_GET["acao"] ?? "";

if (metodo() === "PUT" && $acao === "senha") {
    $dados = dadosRecebidos();
    $atual = (string) ($dados["senha_atual"] ?? "");
    $nova = (string) ($dados["nova_senha"] ?? "");
    $registro = consultarUm("SELECT senha FROM usuarios WHERE id = ?", [$usuario["id"]]);

    if (!password_verify($atual, $registro["senha"])) {
        erro("A senha atual não confere.", "senhaAtual");
    }
    if (strlen($nova) < 6) {
        erro("A nova senha precisa ter pelo menos 6 caracteres.", "novaSenha");
    }
    if ($nova === $atual) {
        erro("A nova senha precisa ser diferente da atual.", "novaSenha");
    }
    if ($nova !== ($dados["confirmacao"] ?? "")) {
        erro("A confirmação não confere com a nova senha.", "confirmacaoSenha");
    }

    executar(
        "UPDATE usuarios SET senha = ?, trocar_senha = 0, pedido_senha_em = NULL WHERE id = ?",
        [password_hash($nova, PASSWORD_DEFAULT), $usuario["id"]]
    );
    session_regenerate_id(true);

    responder(["ok" => true]);
}

if (metodo() === "PUT") {
    $dados = dadosRecebidos();
    $nome = trim($dados["nome"] ?? "");
    $email = trim($dados["email"] ?? "");
    $nascimento = trim($dados["nascimento"] ?? "");
    $telefone = trim($dados["telefone"] ?? "");

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        erro("Informe seu nome completo.", "nome");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        erro("Informe um e-mail válido.", "email");
    }
    if (consultar("SELECT id FROM usuarios WHERE email = ? AND id <> ?", [$email, $usuario["id"]])) {
        erro("Esse e-mail já é usado por outra conta.", "email");
    }
    if ($nascimento !== "" && (!dataValida($nascimento) || $nascimento > hoje())) {
        erro("Informe uma data de nascimento válida.", "nascimento");
    }
    if (mb_strlen($telefone) > 20) {
        erro("Telefone muito longo.", "telefone");
    }

    // O perfil (cargo) não pode ser alterado aqui: só o gestor altera, em api/usuarios.php.
    executar(
        "UPDATE usuarios SET nome = ?, email = ?, nascimento = ?, telefone = ? WHERE id = ?",
        [$nome, $email, $nascimento ?: null, $telefone ?: null, $usuario["id"]]
    );

    responder(buscarUsuario($usuario["id"]));
}

responder($usuario);
