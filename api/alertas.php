<?php

require "conexao.php";

$usuario = exigirCargo();

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    if ($usuario["cargo"] !== "gestor") {
        erro("Só o gestor pode apagar alertas.", null, 403);
    }

    executar("DELETE FROM alertas WHERE id = ?", [$_GET["id"] ?? 0]);

    responder(["ok" => true]);
}

$busca = "%" . ($_GET["busca"] ?? "") . "%";

responder(consultar("SELECT * FROM alertas WHERE mensagem LIKE ? ORDER BY data DESC, id DESC", [$busca]));
