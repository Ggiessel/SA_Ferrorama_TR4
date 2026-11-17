<?php

/*
 * Recuperação de senha sem e-mail (ambiente XAMPP local):
 * o usuário informa o e-mail, o pedido fica registrado e o gestor recebe um alerta.
 * O gestor redefine a senha em Usuários e entrega a senha temporária ao usuário,
 * que é obrigado a trocá-la no próximo acesso.
 */

require "conexao.php";

if (metodo() !== "POST") {
    erro("Use POST.", null, 405);
}

$email = trim(dadosRecebidos()["email"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    erro("Digite um e-mail válido.", "emailRecuperar");
}

$registro = consultarUm("SELECT id, nome, ativo, pedido_senha_em FROM usuarios WHERE email = ?", [$email]);

// Evita repetir o alerta para o mesmo usuário dentro de uma hora.
if ($registro && (int) $registro["ativo"] && (!$registro["pedido_senha_em"] || strtotime($registro["pedido_senha_em"]) < time() - 3600)) {
    executar("UPDATE usuarios SET pedido_senha_em = ? WHERE id = ?", [date("Y-m-d H:i:s"), $registro["id"]]);
    criarAlerta("Pedido de redefinição de senha: " . $registro["nome"] . " (" . $email . ").", "atencao", ["origem" => "sistema"]);
}

// A resposta é sempre a mesma, para não revelar quais e-mails têm conta.
responder(["mensagem" => "Se o e-mail estiver cadastrado, o gestor foi avisado e vai redefinir sua senha."]);
