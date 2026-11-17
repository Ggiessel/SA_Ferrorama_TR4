<?php

/*
 * Núcleo das APIs do Ferrorama TR4: conexão PDO, sessão, respostas JSON,
 * validações e controle de permissões. Toda API inclui este arquivo.
 */

ini_set("display_errors", 0);
date_default_timezone_set("America/Sao_Paulo");

session_name("TR4SESSAO");
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "httponly" => true,
    "samesite" => "Lax",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
]);
ini_set("session.use_strict_mode", 1);
ini_set("session.use_only_cookies", 1);
session_start();

header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-store");

require_once __DIR__ . "/../includes/limites.php";

$host = "localhost";
$banco = "ferrorama_tr4";
$usuarioBanco = "root";
$senhaBanco = "";

const TEMPO_SESSAO = 2 * 60 * 60; // 2 horas sem uso encerram a sessão

try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuarioBanco, $senhaBanco, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("SET time_zone = '" . date("P") . "'");
} catch (PDOException $e) {
    erro("Não foi possível conectar ao banco. Ligue o MySQL no XAMPP e importe o arquivo ferrorama_tr4.sql.", null, 500);
}

set_exception_handler(function ($e) {
    error_log("Ferrorama TR4: " . $e->getMessage());
    erro("Não foi possível concluir a operação. Tente novamente.", null, 500);
});

/*
 * Proteção contra requisições vindas de outros sites (CSRF): toda operação que altera
 * dados precisa do cabeçalho X-Requested-With, que um formulário de outro site não envia.
 */
if (!in_array($_SERVER["REQUEST_METHOD"] ?? "GET", ["GET", "HEAD", "OPTIONS"])) {
    if (($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") !== "fetch") {
        erro("Requisição recusada.", null, 403);
    }
}

// ---------------------------------------------------------------------
// Respostas
// ---------------------------------------------------------------------

function responder($dados, $status = 200)
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro($mensagem, $campo = null, $status = 400)
{
    responder(["erro" => $mensagem, "campo" => $campo], $status);
}

function dadosRecebidos()
{
    $dados = json_decode(file_get_contents("php://input"), true);
    return is_array($dados) ? $dados : [];
}

function metodo()
{
    return $_SERVER["REQUEST_METHOD"] ?? "GET";
}

// ---------------------------------------------------------------------
// Banco
// ---------------------------------------------------------------------

function consultar($sql, $valores = [])
{
    global $pdo;
    $comando = $pdo->prepare($sql);
    $comando->execute(array_values($valores));
    return $comando->fetchAll();
}

function consultarUm($sql, $valores = [])
{
    $resultado = consultar($sql, $valores);
    return $resultado[0] ?? null;
}

function executar($sql, $valores = [])
{
    global $pdo;
    $comando = $pdo->prepare($sql);
    $comando->execute(array_values($valores));
    return $comando->rowCount();
}

function ultimoId()
{
    global $pdo;
    return (int) $pdo->lastInsertId();
}

// ---------------------------------------------------------------------
// Validação
// ---------------------------------------------------------------------

function inteiro($valor)
{
    return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int) $valor : 0;
}

function dataValida($data)
{
    if (!is_string($data) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $data)) {
        return false;
    }
    [$ano, $mes, $dia] = array_map("intval", explode("-", $data));
    return checkdate($mes, $dia, $ano);
}

function dataHoraValida($valor)
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = str_replace("T", " ", trim($valor));
    if (preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/", $valor)) {
        $valor .= ":00";
    }
    $data = DateTime::createFromFormat("Y-m-d H:i:s", $valor);
    return $data && $data->format("Y-m-d H:i:s") === $valor ? $valor : null;
}

/**
 * Monta as condições de período para uma coluna DATETIME/DATE.
 * A data final é inclusiva: vai até 23:59:59 do dia informado.
 */
function filtroPeriodo($coluna, $dataInicial, $dataFinal, &$condicoes, &$valores)
{
    if ($dataInicial !== "" && $dataInicial !== null && !dataValida($dataInicial)) {
        erro("Data inicial inválida.", "dataInicial");
    }
    if ($dataFinal !== "" && $dataFinal !== null && !dataValida($dataFinal)) {
        erro("Data final inválida.", "dataFinal");
    }
    if (dataValida($dataInicial) && dataValida($dataFinal) && $dataInicial > $dataFinal) {
        erro("A data inicial não pode ser depois da data final.", "dataFinal");
    }
    if (dataValida($dataInicial)) {
        $condicoes[] = "$coluna >= ?";
        $valores[] = $dataInicial . " 00:00:00";
    }
    if (dataValida($dataFinal)) {
        $condicoes[] = "$coluna < ?";
        $valores[] = date("Y-m-d", strtotime($dataFinal . " +1 day")) . " 00:00:00";
    }
}

function onde($condicoes)
{
    return $condicoes ? " WHERE " . implode(" AND ", $condicoes) : "";
}

// ---------------------------------------------------------------------
// Sessão e permissões
// ---------------------------------------------------------------------

function encerrarSessao()
{
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires" => time() - 3600,
        "path" => $cookie["path"],
        "domain" => $cookie["domain"],
        "secure" => $cookie["secure"],
        "httponly" => $cookie["httponly"],
        "samesite" => $cookie["samesite"] ?? "Lax"
    ]);
    session_destroy();
}

function iniciarSessaoUsuario($id)
{
    session_regenerate_id(true);
    $_SESSION["usuario_id"] = (int) $id;
    $_SESSION["ultimo_acesso"] = time();
}

function buscarUsuario($id)
{
    return consultarUm(
        "SELECT usuarios.id, usuarios.nome, usuarios.email, usuarios.cargo, usuarios.funcao, usuarios.trem_id,
                usuarios.ativo, usuarios.trocar_senha, usuarios.nascimento, usuarios.ativo_desde, usuarios.telefone,
                usuarios.cpf, usuarios.endereco, trens.nome AS trem
         FROM usuarios
         LEFT JOIN trens ON trens.id = usuarios.trem_id
         WHERE usuarios.id = ?",
        [$id]
    );
}

/**
 * Devolve o usuário conectado, sempre relido do banco: assim uma desativação
 * ou mudança de perfil vale na próxima requisição, sem esperar novo login.
 */
function usuarioLogado()
{
    static $usuario = null;

    if ($usuario) {
        return $usuario;
    }
    if (empty($_SESSION["usuario_id"])) {
        erro("Entre na sua conta para continuar.", null, 401);
    }
    if (time() - ($_SESSION["ultimo_acesso"] ?? 0) > TEMPO_SESSAO) {
        encerrarSessao();
        erro("Sua sessão expirou. Entre novamente.", null, 401);
    }

    $usuario = buscarUsuario($_SESSION["usuario_id"]);

    if (!$usuario || !(int) $usuario["ativo"]) {
        encerrarSessao();
        erro("Sua conta está desativada. Procure o gestor.", null, 401);
    }

    $_SESSION["ultimo_acesso"] = time();
    $usuario["id"] = (int) $usuario["id"];
    $usuario["trem_id"] = $usuario["trem_id"] !== null ? (int) $usuario["trem_id"] : null;

    return $usuario;
}

/** Exige sessão e, se informados, um dos perfis. Sem perfis: qualquer usuário logado. */
function exigirCargo(...$cargos)
{
    $usuario = usuarioLogado();
    if ($cargos && !in_array($usuario["cargo"], $cargos, true)) {
        erro("Você não tem permissão para isso.", null, 403);
    }
    return $usuario;
}

function eGestor($usuario)
{
    return $usuario["cargo"] === "gestor";
}

/**
 * Para o maquinista, devolve o id do trem dele (ou -1 se não tiver trem, para que
 * nenhuma linha seja retornada). Para o gestor, devolve null (sem restrição).
 */
function restricaoTrem($usuario)
{
    if ($usuario["cargo"] === "maquinista") {
        return $usuario["trem_id"] ?: -1;
    }
    return null;
}

/** Bloqueia o maquinista de acessar um trem que não é o dele. */
function exigirAcessoTrem($usuario, $tremId)
{
    $restricao = restricaoTrem($usuario);
    if ($restricao !== null && (int) $tremId !== (int) $restricao) {
        erro("Você só pode acessar informações do seu trem.", null, 403);
    }
}

// ---------------------------------------------------------------------
// Alertas e utilidades
// ---------------------------------------------------------------------

function criarAlerta($mensagem, $nivel = "normal", $extra = [])
{
    executar(
        "INSERT INTO alertas (mensagem, nivel, origem, situacao, publico, trem_id, sensor_id, leitura_id, criado_por, data)
         VALUES (?, ?, ?, 'ativo', ?, ?, ?, ?, ?, ?)",
        [
            mb_substr($mensagem, 0, 255),
            $nivel,
            $extra["origem"] ?? "sistema",
            !empty($extra["publico"]) ? 1 : 0,
            $extra["trem_id"] ?? null,
            $extra["sensor_id"] ?? null,
            $extra["leitura_id"] ?? null,
            $extra["criado_por"] ?? null,
            date("Y-m-d H:i:s")
        ]
    );
    return ultimoId();
}

function hoje()
{
    return date("Y-m-d");
}

function formatarData($data)
{
    return date("d/m/Y", strtotime($data));
}

function numeroBr($valor, $casas = 2)
{
    return number_format((float) $valor, $casas, ",", ".");
}

/** Modelos de sensor aceitos, com a grandeza medida e a chave dos limites. */
function tiposSensor()
{
    return [
        ["tipo" => "DS18B20", "grandeza" => "Temperatura do motor", "chave" => "temperatura_motor"],
        ["tipo" => "MAX6675", "grandeza" => "Temperatura dos gases de escape", "chave" => "temperatura_escape"],
        ["tipo" => "MLX90614", "grandeza" => "Temperatura do rolamento", "chave" => "temperatura_rolamento"],
        ["tipo" => "ADXL345", "grandeza" => "Vibração", "chave" => "vibracao"],
        ["tipo" => "MPU-6050", "grandeza" => "Vibração e inclinação", "chave" => "vibracao"],
        ["tipo" => "A3144", "grandeza" => "Velocidade (sensor Hall na roda)", "chave" => "velocidade"],
        ["tipo" => "NEO-6M", "grandeza" => "Localização e velocidade (GPS)", "chave" => "velocidade"],
        ["tipo" => "Omnicomm LLS 5", "grandeza" => "Nível e consumo de combustível", "chave" => "consumo"],
        ["tipo" => "MPX5700AP", "grandeza" => "Pressão do freio", "chave" => "pressao_freio"],
        ["tipo" => "DHT22", "grandeza" => "Temperatura e umidade da carga", "chave" => "temperatura_carga"]
    ];
}

function tipoSensor($tipo)
{
    foreach (tiposSensor() as $sensor) {
        if ($sensor["tipo"] === $tipo) {
            $sensor["unidade"] = LIMITES_SENSORES[$sensor["chave"]]["unidade"];
            return $sensor;
        }
    }
    return null;
}
