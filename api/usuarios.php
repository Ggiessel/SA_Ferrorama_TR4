<?php

/*
 * Administração de usuários (somente gestor/administrador).
 * GET              lista (filtros: busca, cargo, ativo)
 * GET ?id=         um usuário
 * POST             cadastra
 * PUT ?id=         edita dados e perfil
 * PATCH ?id=&acao=ativar|desativar|redefinir_senha
 */

require "conexao.php";

$gestor = exigirCargo("gestor");
$id = inteiro($_GET["id"] ?? 0);
$acao = $_GET["acao"] ?? "";

const CARGOS = ["gestor", "maquinista", "cliente"];

function gestoresAtivos($excetoId = 0)
{
    return (int) consultarUm(
        "SELECT COUNT(*) AS total FROM usuarios WHERE cargo = 'gestor' AND ativo = 1 AND id <> ?",
        [$excetoId]
    )["total"];
}

function usuarioOuErro($id)
{
    $usuario = buscarUsuario($id);
    if (!$usuario) {
        erro("Usuário não encontrado.", null, 404);
    }
    return $usuario;
}

function senhaTemporaria()
{
    $letras = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
    $senha = "";
    for ($i = 0; $i < 10; $i++) {
        $senha .= $letras[random_int(0, strlen($letras) - 1)];
    }
    return $senha;
}

function dadosDoUsuario($id = 0)
{
    $dados = dadosRecebidos();
    $campos = ["nome", "email", "cargo", "funcao", "telefone", "cpf", "nascimento", "endereco", "ativo_desde"];
    foreach ($campos as $campo) {
        $dados[$campo] = trim((string) ($dados[$campo] ?? ""));
    }
    $tremId = inteiro($dados["trem_id"] ?? 0);

    if (mb_strlen($dados["nome"]) < 3 || mb_strlen($dados["nome"]) > 100) {
        erro("Informe o nome completo.", "nomeUsuario");
    }
    if (!filter_var($dados["email"], FILTER_VALIDATE_EMAIL) || strlen($dados["email"]) > 120) {
        erro("Informe um e-mail válido.", "emailUsuario");
    }
    if (consultar("SELECT id FROM usuarios WHERE email = ? AND id <> ?", [$dados["email"], $id])) {
        erro("Esse e-mail já é usado por outro usuário.", "emailUsuario");
    }
    if (!in_array($dados["cargo"], CARGOS, true)) {
        erro("Escolha o perfil do usuário.", "cargoUsuario");
    }
    if ($dados["cargo"] === "maquinista" && $tremId > 0 && !consultar("SELECT id FROM trens WHERE id = ?", [$tremId])) {
        erro("Escolha um trem válido.", "tremUsuario");
    }
    if ($dados["cpf"] !== "" && !preg_match("/^\d{3}\.\d{3}\.\d{3}-\d{2}$/", $dados["cpf"])) {
        erro("Informe o CPF no formato 000.000.000-00.", "cpfUsuario");
    }
    if ($dados["cpf"] !== "" && consultar("SELECT id FROM usuarios WHERE cpf = ? AND id <> ?", [$dados["cpf"], $id])) {
        erro("Esse CPF já é de outro usuário.", "cpfUsuario");
    }
    if ($dados["nascimento"] !== "" && (!dataValida($dados["nascimento"]) || $dados["nascimento"] > hoje())) {
        erro("Informe uma data de nascimento válida.", "nascimentoUsuario");
    }
    if ($dados["ativo_desde"] !== "" && !dataValida($dados["ativo_desde"])) {
        erro("Informe uma data válida.", "ativoDesdeUsuario");
    }
    if (mb_strlen($dados["funcao"]) > 60 || mb_strlen($dados["telefone"]) > 20 || mb_strlen($dados["endereco"]) > 200) {
        erro("Algum campo passou do tamanho máximo.", null);
    }

    return [
        "nome" => $dados["nome"],
        "email" => $dados["email"],
        "cargo" => $dados["cargo"],
        "funcao" => $dados["funcao"] ?: null,
        "trem_id" => $dados["cargo"] === "maquinista" && $tremId > 0 ? $tremId : null,
        "telefone" => $dados["telefone"] ?: null,
        "cpf" => $dados["cpf"] ?: null,
        "nascimento" => $dados["nascimento"] ?: null,
        "endereco" => $dados["endereco"] ?: null,
        "ativo_desde" => $dados["ativo_desde"] ?: hoje(),
        "senha" => (string) ($dados["senha"] ?? "")
    ];
}

if (metodo() === "POST") {
    $novo = dadosDoUsuario();
    $temporaria = false;

    if ($novo["senha"] === "") {
        $novo["senha"] = senhaTemporaria();
        $temporaria = true;
    } elseif (strlen($novo["senha"]) < 6) {
        erro("A senha precisa ter pelo menos 6 caracteres.", "senhaUsuario");
    }

    executar(
        "INSERT INTO usuarios (nome, email, senha, cargo, funcao, trem_id, ativo, trocar_senha, telefone, cpf, nascimento, endereco, ativo_desde)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?, ?)",
        [
            $novo["nome"], $novo["email"], password_hash($novo["senha"], PASSWORD_DEFAULT), $novo["cargo"], $novo["funcao"],
            $novo["trem_id"], $novo["telefone"], $novo["cpf"], $novo["nascimento"], $novo["endereco"], $novo["ativo_desde"]
        ]
    );

    responder([
        "id" => ultimoId(),
        "senha_temporaria" => $temporaria ? $novo["senha"] : null
    ], 201);
}

if (metodo() === "PUT") {
    $atual = usuarioOuErro($id);
    $novo = dadosDoUsuario($id);

    // Regras de perfil verificadas no servidor.
    if ($atual["id"] == $gestor["id"] && $novo["cargo"] !== "gestor") {
        erro("Você não pode tirar o seu próprio perfil de gestor.", "cargoUsuario");
    }
    if ($atual["cargo"] === "gestor" && $novo["cargo"] !== "gestor" && (int) $atual["ativo"] && gestoresAtivos($id) === 0) {
        erro("Este é o último gestor ativo. Defina outro gestor antes de alterar este perfil.", "cargoUsuario");
    }

    executar(
        "UPDATE usuarios SET nome = ?, email = ?, cargo = ?, funcao = ?, trem_id = ?, telefone = ?, cpf = ?, nascimento = ?, endereco = ?, ativo_desde = ? WHERE id = ?",
        [
            $novo["nome"], $novo["email"], $novo["cargo"], $novo["funcao"], $novo["trem_id"], $novo["telefone"],
            $novo["cpf"], $novo["nascimento"], $novo["endereco"], $novo["ativo_desde"], $id
        ]
    );

    responder(buscarUsuario($id));
}

if (metodo() === "PATCH") {
    $alvo = usuarioOuErro($id);

    if ($acao === "desativar") {
        if ($alvo["id"] == $gestor["id"]) {
            erro("Você não pode desativar a sua própria conta.", null, 409);
        }
        if ($alvo["cargo"] === "gestor" && gestoresAtivos($id) === 0) {
            erro("Não é possível desativar o último gestor ativo.", null, 409);
        }
        executar("UPDATE usuarios SET ativo = 0 WHERE id = ?", [$id]);
        responder(buscarUsuario($id));
    }

    if ($acao === "ativar") {
        executar("UPDATE usuarios SET ativo = 1 WHERE id = ?", [$id]);
        responder(buscarUsuario($id));
    }

    if ($acao === "redefinir_senha") {
        $senha = senhaTemporaria();
        executar(
            "UPDATE usuarios SET senha = ?, trocar_senha = 1, pedido_senha_em = NULL WHERE id = ?",
            [password_hash($senha, PASSWORD_DEFAULT), $id]
        );
        // Resolve alertas de pedido de senha desse usuário.
        executar(
            "UPDATE alertas SET situacao = 'resolvido', resolvido_em = ?, resolvido_por = ?
             WHERE situacao = 'ativo' AND origem = 'sistema' AND mensagem LIKE ?",
            [date("Y-m-d H:i:s"), $gestor["id"], "Pedido de redefinição de senha:%(" . $alvo["email"] . ")."]
        );
        responder(["senha_temporaria" => $senha]);
    }

    erro("Ação inválida.");
}

if (metodo() !== "GET") {
    erro("Método não permitido.", null, 405);
}

if ($id) {
    responder(usuarioOuErro($id));
}

$condicoes = [];
$valores = [];
$busca = trim($_GET["busca"] ?? "");

if ($busca !== "") {
    $condicoes[] = "(usuarios.nome LIKE ? OR usuarios.email LIKE ? OR usuarios.funcao LIKE ?)";
    array_push($valores, "%$busca%", "%$busca%", "%$busca%");
}
if (in_array($_GET["cargo"] ?? "", CARGOS, true)) {
    $condicoes[] = "usuarios.cargo = ?";
    $valores[] = $_GET["cargo"];
}
if (in_array($_GET["ativo"] ?? "", ["0", "1"], true)) {
    $condicoes[] = "usuarios.ativo = ?";
    $valores[] = (int) $_GET["ativo"];
}

responder(consultar(
    "SELECT usuarios.id, usuarios.nome, usuarios.email, usuarios.cargo, usuarios.funcao, usuarios.trem_id, usuarios.ativo,
            usuarios.trocar_senha, usuarios.pedido_senha_em, usuarios.telefone, usuarios.ativo_desde, trens.nome AS trem
     FROM usuarios
     LEFT JOIN trens ON trens.id = usuarios.trem_id" . onde($condicoes) . "
     ORDER BY usuarios.ativo DESC, usuarios.nome",
    $valores
));
