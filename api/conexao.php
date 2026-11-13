<?php

ini_set("display_errors", 0);
session_start();
header("Content-Type: application/json; charset=utf-8");
date_default_timezone_set("America/Sao_Paulo");

$host = "localhost";
$banco = "ferrorama_tr4";
$usuarioBanco = "root";
$senhaBanco = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuarioBanco, $senhaBanco);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    erro("Não foi possível conectar ao banco. Ligue o MySQL no XAMPP e importe o arquivo ferrorama_tr4.sql.", null, 500);
}

set_exception_handler(function ($e) {
    erro("Não foi possível concluir a operação. Tente novamente.", null, 500);
});

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
    return json_decode(file_get_contents("php://input"), true) ?? [];
}

function consultar($sql, $valores = [])
{
    global $pdo;
    $comando = $pdo->prepare($sql);
    $comando->execute($valores);
    return $comando->fetchAll();
}

function executar($sql, $valores = [])
{
    global $pdo;
    $comando = $pdo->prepare($sql);
    $comando->execute($valores);
    return $comando->rowCount();
}

function usuarioLogado()
{
    if (!isset($_SESSION["usuario"])) {
        erro("Entre na sua conta para continuar.", null, 401);
    }
    return $_SESSION["usuario"];
}

function exigirCargo(...$cargos)
{
    $usuario = usuarioLogado();
    if (!$usuario["cargo"] || ($cargos && !in_array($usuario["cargo"], $cargos))) {
        erro("Você não tem permissão para isso.", null, 403);
    }
    return $usuario;
}

function criarAlerta($mensagem, $tipo = "info")
{
    executar("INSERT INTO alertas (mensagem, tipo, data) VALUES (?, ?, ?)", [$mensagem, $tipo, date("Y-m-d H:i:s")]);
}

function hoje()
{
    return date("Y-m-d");
}

function formatarData($data)
{
    return date("d/m/Y", strtotime($data));
}

function tiposSensor()
{
    return [
        ["tipo" => "DS18B20", "grandeza" => "Temperatura do motor"],
        ["tipo" => "MAX6675", "grandeza" => "Temperatura dos gases de escape"],
        ["tipo" => "MLX90614", "grandeza" => "Temperatura do rolamento"],
        ["tipo" => "ADXL345", "grandeza" => "Vibração"],
        ["tipo" => "MPU-6050", "grandeza" => "Vibração e inclinação"],
        ["tipo" => "A3144", "grandeza" => "Velocidade (sensor Hall na roda)"],
        ["tipo" => "NEO-6M", "grandeza" => "Localização e velocidade (GPS)"],
        ["tipo" => "Omnicomm LLS 5", "grandeza" => "Nível e consumo de combustível"],
        ["tipo" => "MPX5700AP", "grandeza" => "Pressão do freio"],
        ["tipo" => "DHT22", "grandeza" => "Temperatura e umidade da carga"]
    ];
}
