<?php

/*
 * Testes automatizados das APIs do Ferrorama TR4.
 *
 * Uso (com o banco recém-importado de banco-de-dados/ferrorama_tr4.sql e o Apache ligado):
 *     php testes/testes-api.php http://localhost/SA_Ferrorama_TR4
 * No XAMPP do Windows:  C:\xampp\php\php.exe testes\testes-api.php http://localhost/SA_Ferrorama_TR4
 *
 * Os testes criam e alteram registros (trens, sensores, usuários...). Reimporte o banco
 * depois de executá-los se quiser voltar aos dados de exemplo.
 * Gera o arquivo testes/resultado-testes-api.md com nome, esperado, obtido, status e data.
 */

$base = rtrim($argv[1] ?? "http://localhost/SA_Ferrorama_TR4", "/") . "/api/";
$pdo = new PDO("mysql:host=localhost;dbname=ferrorama_tr4;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
$pdo->exec("SET time_zone = '-03:00'");
date_default_timezone_set("America/Sao_Paulo");

$resultados = [];
$cookies = [];

/** Requisição HTTP com sessão (cookie) por "cliente" de teste. */
function req($quem, $metodo, $caminho, $dados = null, $semCabecalho = false)
{
    global $base, $cookies;
    $ch = curl_init($base . $caminho);
    $arquivo = sys_get_temp_dir() . "/tr4-teste-$quem.cookie";
    if (!isset($cookies[$quem])) {
        @unlink($arquivo);
        $cookies[$quem] = $arquivo;
    }
    $cabecalhos = ["Content-Type: application/json"];
    if (!$semCabecalho) {
        $cabecalhos[] = "X-Requested-With: fetch";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $arquivo,
        CURLOPT_COOKIEFILE => $arquivo,
        CURLOPT_HTTPHEADER => $cabecalhos,
        CURLOPT_PROXY => ""
    ]);
    if ($dados !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    }
    $resposta = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tamanhoCabecalho = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $cabecalho = substr($resposta, 0, $tamanhoCabecalho);
    $corpo = substr($resposta, $tamanhoCabecalho);
    return ["status" => $status, "json" => json_decode($corpo, true), "corpo" => $corpo, "cabecalho" => $cabecalho];
}

function login($quem, $email, $senha = "123456")
{
    return req($quem, "POST", "login.php", ["email" => $email, "senha" => $senha]);
}

function sql($consulta, $valores = [])
{
    global $pdo;
    $c = $pdo->prepare($consulta);
    $c->execute($valores);
    return $c->fetchAll();
}

function teste($area, $nome, $esperado, callable $executar)
{
    global $resultados;
    try {
        [$ok, $obtido] = $executar();
    } catch (Throwable $e) {
        [$ok, $obtido] = [false, "Exceção: " . $e->getMessage()];
    }
    $resultados[] = [$area, $nome, $esperado, $obtido, $ok ? "Aprovado" : "Reprovado"];
    printf("%-9s %-58s %s\n", $ok ? "[OK]" : "[FALHOU]", mb_substr($nome, 0, 58), $ok ? "" : "-> $obtido");
}

// ===================================================================== LOGIN / LOGOUT
teste("Login", "Login com senha errada é recusado", "401 e mensagem genérica", function () {
    $r = login("anon", "gestor@tr4.com", "errada");
    return [$r["status"] === 401 && $r["json"]["erro"] === "E-mail ou senha incorretos.", $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Login", "Login com e-mail inexistente dá a mesma mensagem", "401 e a mesma mensagem do teste anterior", function () {
    $r = login("anon", "naoexiste@tr4.com", "123456");
    return [$r["status"] === 401 && $r["json"]["erro"] === "E-mail ou senha incorretos.", $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Login", "Login do gestor com dados corretos", "200 e perfil gestor", function () {
    $r = login("gestor", "gestor@tr4.com");
    return [$r["status"] === 200 && $r["json"]["cargo"] === "gestor", $r["status"] . " - perfil " . ($r["json"]["cargo"] ?? "?")];
});
login("maquinista", "maquinista@tr4.com");
login("cliente", "cliente@tr4.com");

teste("Login", "Senhas gravadas com hash bcrypt", "Todas as senhas começam com \$2y\$", function () {
    $sem = sql("SELECT COUNT(*) AS t FROM usuarios WHERE senha NOT LIKE '\$2y\$%'")[0]["t"];
    return [(int) $sem === 0, "$sem senhas sem hash"];
});
teste("Login", "Usuário desativado não consegue entrar", "403 com aviso de conta desativada", function () {
    $r = login("paulo", "paulo.alves@tr4.com");
    return [$r["status"] === 403 && str_contains($r["json"]["erro"], "desativada"), $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Permissões", "API sem sessão é bloqueada", "401 no painel", function () {
    $r = req("anon2", "GET", "painel.php");
    return [$r["status"] === 401, (string) $r["status"]];
});
teste("Segurança", "Alteração sem cabeçalho X-Requested-With (CSRF) é recusada", "403", function () {
    $r = req("gestor", "POST", "alertas.php", ["mensagem" => "teste csrf", "nivel" => "normal"], true);
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Cadastro", "Cadastro público sempre cria cliente (ignora cargo enviado)", "201 e perfil cliente", function () {
    $email = "novo" . time() . "@tr4.com";
    $r = req("novo", "POST", "cadastro.php", ["nome" => "Novo Teste", "email" => $email, "senha" => "abcdef", "confirmacao" => "abcdef", "cargo" => "gestor"]);
    return [$r["status"] === 201 && $r["json"]["cargo"] === "cliente", $r["status"] . " - perfil " . ($r["json"]["cargo"] ?? "?")];
});

// ===================================================================== PERMISSÕES
teste("Permissões", "Cliente não acessa leituras", "403", function () {
    return [req("cliente", "GET", "leituras.php")["status"] === 403, (string) req("cliente", "GET", "leituras.php")["status"]];
});
teste("Permissões", "Cliente não acessa o painel nem relatórios", "403 nos dois", function () {
    $a = req("cliente", "GET", "painel.php")["status"];
    $b = req("cliente", "GET", "relatorios.php?tipo=desempenho")["status"];
    return [$a === 403 && $b === 403, "painel $a, relatórios $b"];
});
teste("Permissões", "Cliente vê somente avisos públicos", "Nenhum alerta com publico = 0", function () {
    $r = req("cliente", "GET", "alertas.php");
    $privados = array_filter($r["json"], fn($a) => !(int) $a["publico"]);
    return [$r["status"] === 200 && !$privados && count($r["json"]) > 0, count($r["json"]) . " avisos, " . count($privados) . " privados"];
});
teste("Permissões", "Maquinista não cadastra trem (requisição direta)", "403", function () {
    $r = req("maquinista", "POST", "trens.php", ["codigo" => "77", "nome" => "Trem 77", "modelo" => "X", "ano_fabricacao" => 2010, "capacidade_toneladas" => 10, "status" => "disponivel", "consumo" => 10]);
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Permissões", "Maquinista não exclui sensor", "403", function () {
    $r = req("maquinista", "DELETE", "sensores.php?id=1");
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Permissões", "Maquinista lista apenas o próprio trem", "1 trem (Trem 08)", function () {
    $r = req("maquinista", "GET", "trens.php");
    return [count($r["json"]) === 1 && $r["json"][0]["nome"] === "Trem 08", count($r["json"]) . " trem(ns): " . implode(", ", array_column($r["json"], "nome"))];
});
teste("Permissões", "Maquinista não abre trem de outro maquinista pela URL", "403", function () {
    $r = req("maquinista", "GET", "trens.php?id=4");
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Permissões", "Maquinista não vê leituras de outro trem mesmo filtrando", "0 leituras de outros trens", function () {
    $r = req("maquinista", "GET", "leituras.php?trem_id=4");
    $outros = array_filter($r["json"]["leituras"], fn($l) => (int) $l["trem_id"] !== 1);
    return [$r["status"] === 200 && !$outros, count($r["json"]["leituras"]) . " leituras, " . count($outros) . " de outros trens"];
});
teste("Permissões", "Maquinista não usa o simulador", "403", function () {
    $r = req("maquinista", "POST", "leituras.php?acao=simular", ["trem_id" => 1, "quantidade" => 2]);
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Permissões", "Maquinista não acessa a administração de usuários", "403", function () {
    return [req("maquinista", "GET", "usuarios.php")["status"] === 403, (string) req("maquinista", "GET", "usuarios.php")["status"]];
});
teste("Permissões", "Usuário não altera o próprio perfil pela API do perfil", "Perfil continua cliente", function () {
    req("cliente", "PUT", "usuario.php", ["nome" => "Cliente Demonstração", "email" => "cliente@tr4.com", "cargo" => "gestor"]);
    $cargo = sql("SELECT cargo FROM usuarios WHERE email = 'cliente@tr4.com'")[0]["cargo"];
    return [$cargo === "cliente", "perfil no banco: $cargo"];
});

// ===================================================================== TRENS
$tremId = 0;
teste("Trens", "Cadastrar trem completo", "201 e trem gravado no banco", function () use (&$tremId) {
    $r = req("gestor", "POST", "trens.php", ["codigo" => "90", "nome" => "Trem 90", "modelo" => "GE AC44i", "ano_fabricacao" => 2015, "capacidade_toneladas" => 6000, "status" => "disponivel", "consumo" => 13]);
    $tremId = $r["json"]["id"] ?? 0;
    $existe = sql("SELECT codigo FROM trens WHERE id = ?", [$tremId]);
    return [$r["status"] === 201 && $existe && $existe[0]["codigo"] === "090", $r["status"] . " - código gravado " . ($existe[0]["codigo"] ?? "nenhum")];
});
teste("Trens", "Código de trem repetido é recusado", "400 com mensagem no campo codigoTrem", function () {
    $r = req("gestor", "POST", "trens.php", ["codigo" => "8", "nome" => "Trem X", "modelo" => "X", "ano_fabricacao" => 2015, "capacidade_toneladas" => 10, "status" => "disponivel", "consumo" => 10]);
    return [$r["status"] === 400 && $r["json"]["campo"] === "codigoTrem", $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Trens", "Editar trem", "Modelo alterado no banco", function () use (&$tremId) {
    $r = req("gestor", "PUT", "trens.php?id=$tremId", ["codigo" => "90", "nome" => "Trem 90", "modelo" => "EMD SD70ACe", "ano_fabricacao" => 2015, "capacidade_toneladas" => 6000, "status" => "disponivel", "consumo" => 13]);
    $modelo = sql("SELECT modelo FROM trens WHERE id = ?", [$tremId])[0]["modelo"];
    return [$r["status"] === 200 && $modelo === "EMD SD70ACe", $r["status"] . " - modelo $modelo"];
});
teste("Trens", "Situação 'em rota' exige rota", "400 no campo rotaTrem", function () use (&$tremId) {
    $r = req("gestor", "PATCH", "trens.php?id=$tremId", ["status" => "em_rota"]);
    return [$r["status"] === 400 && $r["json"]["campo"] === "rotaTrem", $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Trens", "Alterar situação operacional", "Trem fica inativo", function () use (&$tremId) {
    $r = req("gestor", "PATCH", "trens.php?id=$tremId", ["status" => "inativo"]);
    return [$r["status"] === 200 && $r["json"]["status"] === "inativo", $r["status"] . " - " . ($r["json"]["status"] ?? "?")];
});

// ===================================================================== SENSORES E LEITURAS
$sensorId = 0;
teste("Sensores", "Cadastrar sensor ativo e associar ao trem", "201 e sensor ligado ao trem", function () use (&$sensorId, &$tremId) {
    $r = req("gestor", "POST", "sensores.php", ["tipo" => "DS18B20", "trem_id" => $tremId, "status" => "ativo"]);
    $sensorId = $r["json"]["id"] ?? 0;
    $s = sql("SELECT trem_id, chave FROM sensores WHERE id = ?", [$sensorId]);
    return [$r["status"] === 201 && (int) $s[0]["trem_id"] === $tremId && $s[0]["chave"] === "temperatura_motor", $r["status"] . " - chave " . ($s[0]["chave"] ?? "?")];
});
teste("Sensores", "Sensor sem leituras aparece na lista sem valor", "ultimo_valor nulo e 0 leituras", function () use (&$sensorId) {
    $r = req("gestor", "GET", "sensores.php?id=$sensorId");
    return [$r["json"]["ultimo_valor"] === null && (int) $r["json"]["total_leituras"] === 0, "valor " . var_export($r["json"]["ultimo_valor"], true) . ", " . $r["json"]["total_leituras"] . " leituras"];
});
teste("Sensores", "Sensor repetido no mesmo trem é recusado", "400", function () use (&$tremId) {
    $r = req("gestor", "POST", "sensores.php", ["tipo" => "DS18B20", "trem_id" => $tremId, "status" => "ativo"]);
    return [$r["status"] === 400, $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Sensores", "Editar sensor (observação)", "Observação gravada", function () use (&$sensorId, &$tremId) {
    $r = req("gestor", "PUT", "sensores.php?id=$sensorId", ["tipo" => "DS18B20", "trem_id" => $tremId, "status" => "ativo", "observacao" => "Instalado no bloco 2"]);
    return [$r["status"] === 200 && $r["json"]["observacao"] === "Instalado no bloco 2", (string) $r["status"]];
});
teste("Leituras", "Leitura crítica gera alerta crítico ligado ao sensor e ao trem", "nível crítico e 1 alerta ativo do sensor", function () use (&$sensorId, &$tremId) {
    $r = req("gestor", "POST", "leituras.php", ["sensor_id" => $sensorId, "valor" => 111.5]);
    $a = sql("SELECT nivel, trem_id FROM alertas WHERE sensor_id = ? AND situacao = 'ativo'", [$sensorId]);
    return [$r["json"]["nivel"] === "critico" && count($a) === 1 && $a[0]["nivel"] === "critico" && (int) $a[0]["trem_id"] === $tremId,
        "nível " . ($r["json"]["nivel"] ?? "?") . ", " . count($a) . " alerta(s)"];
});
teste("Leituras", "Leitura de atenção e normal classificadas pelos limites", "atencao para 100 °C e normal para 80 °C", function () use (&$sensorId) {
    $a = req("gestor", "POST", "leituras.php", ["sensor_id" => $sensorId, "valor" => 100])["json"]["nivel"];
    $b = req("gestor", "POST", "leituras.php", ["sensor_id" => $sensorId, "valor" => 80])["json"]["nivel"];
    return [$a === "atencao" && $b === "normal", "$a / $b"];
});
teste("Leituras", "Última leitura do sensor vem do registro mais recente", "Valor 80 (a última gravada)", function () use (&$sensorId) {
    $r = req("gestor", "GET", "sensores.php?id=$sensorId");
    $banco = sql("SELECT valor FROM leituras WHERE sensor_id = ? ORDER BY data_hora DESC, id DESC LIMIT 1", [$sensorId])[0]["valor"];
    return [(float) $r["json"]["ultimo_valor"] === 80.0 && (float) $banco === 80.0, "API " . $r["json"]["ultimo_valor"] . ", banco $banco"];
});
teste("Leituras", "Valor não numérico é recusado", "400", function () use (&$sensorId) {
    $r = req("gestor", "POST", "leituras.php", ["sensor_id" => $sensorId, "valor" => "abc"]);
    return [$r["status"] === 400, (string) $r["status"]];
});
teste("Simulador", "Simulador grava as leituras no banco", "Quantidade gravada = quantidade informada", function () {
    $antes = sql("SELECT COUNT(*) AS t FROM leituras WHERE trem_id = 1")[0]["t"];
    $r = req("gestor", "POST", "leituras.php?acao=simular", ["trem_id" => 1, "quantidade" => 5]);
    $depois = sql("SELECT COUNT(*) AS t FROM leituras WHERE trem_id = 1")[0]["t"];
    return [$r["status"] === 201 && $depois - $antes === $r["json"]["geradas"] && $r["json"]["geradas"] === 5 * $r["json"]["sensores"],
        "geradas " . $r["json"]["geradas"] . ", diferença no banco " . ($depois - $antes)];
});
teste("Leituras", "Filtro por data final é inclusivo", "Leitura de hoje 23:59 incluída com data_final = hoje", function () use (&$sensorId, &$tremId) {
    sql("INSERT INTO leituras (sensor_id, trem_id, data_hora, valor, nivel) VALUES (?, ?, CONCAT(CURDATE(), ' 23:59:30'), 50, 'normal')", [$sensorId, $tremId]);
    $hoje = date("Y-m-d");
    $r = req("gestor", "GET", "leituras.php?sensor_id=$sensorId&data_inicial=$hoje&data_final=$hoje");
    $achou = array_filter($r["json"]["leituras"], fn($l) => substr($l["data_hora"], 11) === "23:59:30");
    return [count($achou) === 1, count($achou) . " leitura das 23:59:30 encontrada(s)"];
});
teste("Leituras", "Data inválida no filtro é recusada", "400", function () {
    $r = req("gestor", "GET", "leituras.php?data_inicial=2026-02-31");
    return [$r["status"] === 400, (string) $r["status"]];
});
teste("Sensores", "Excluir sensor com leituras apenas desativa (mantém histórico)", "Sensor inativo e leituras preservadas", function () use (&$sensorId) {
    $r = req("gestor", "DELETE", "sensores.php?id=$sensorId");
    $s = sql("SELECT status FROM sensores WHERE id = ?", [$sensorId]);
    $l = sql("SELECT COUNT(*) AS t FROM leituras WHERE sensor_id = ?", [$sensorId])[0]["t"];
    return [!empty($r["json"]["desativado"]) && $s[0]["status"] === "inativo" && $l > 0, "situação " . $s[0]["status"] . ", $l leituras"];
});

// ===================================================================== ROTAS E VIAGENS
$rotaId = 0;
teste("Rotas", "Horário inválido (25:00) é recusado", "400 no campo horariosRota", function () {
    $r = req("gestor", "POST", "rotas.php", ["nome" => "Rota 90", "origem" => "Joinville", "destino" => "Blumenau", "distancia_km" => 100, "horarios" => "25:00"]);
    return [$r["status"] === 400 && $r["json"]["campo"] === "horariosRota", $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Rotas", "Cadastrar e editar rota", "Rota criada e aviso atualizado", function () use (&$rotaId) {
    $r = req("gestor", "POST", "rotas.php", ["nome" => "Rota 90", "origem" => "Joinville", "destino" => "Blumenau", "distancia_km" => 100, "horarios" => "07:00, 13:30"]);
    $rotaId = $r["json"]["id"] ?? 0;
    $e = req("gestor", "PUT", "rotas.php?id=$rotaId", ["nome" => "Rota 90", "origem" => "Joinville", "destino" => "Blumenau", "distancia_km" => 100, "horarios" => "07:00", "alerta" => "Obras na via"]);
    $rota = sql("SELECT alerta FROM rotas WHERE id = ?", [$rotaId]);
    $h = sql("SELECT COUNT(*) AS t FROM horarios WHERE rota_id = ?", [$rotaId])[0]["t"];
    return [$r["status"] === 201 && $e["status"] === 200 && $rota[0]["alerta"] === "Obras na via" && (int) $h === 1, "criação {$r['status']}, edição {$e['status']}, $h horário(s)"];
});
teste("Rotas", "Filtro de viagens por data, situação, trem e rota bate com o banco", "Mesma contagem da consulta SQL", function () {
    $ini = date("Y-m-d", strtotime("-10 days"));
    $fim = date("Y-m-d");
    $api = req("gestor", "GET", "viagens.php?data_inicial=$ini&data_final=$fim&status=concluida&trem_id=1&rota_id=4")["json"];
    $banco = sql("SELECT COUNT(*) AS t FROM viagens WHERE partida_prevista >= ? AND partida_prevista < ? AND status = 'concluida' AND trem_id = 1 AND rota_id = 4",
        ["$ini 00:00:00", date("Y-m-d", strtotime("+1 day")) . " 00:00:00"])[0]["t"];
    return [count($api) === (int) $banco && $banco > 0, "API " . count($api) . ", banco $banco"];
});
$viagemId = 0;
teste("Rotas", "Agendar, iniciar e concluir viagem atualiza o trem", "Trem em rota ao iniciar e disponível ao concluir", function () use (&$rotaId, &$viagemId) {
    $partida = date("Y-m-d H:i", strtotime("+1 hour"));
    $chegada = date("Y-m-d H:i", strtotime("+3 hours"));
    $r = req("gestor", "POST", "viagens.php", ["rota_id" => $rotaId, "trem_id" => 3, "partida_prevista" => $partida, "chegada_prevista" => $chegada]);
    $viagemId = $r["json"]["id"] ?? 0;
    req("gestor", "PATCH", "viagens.php?id=$viagemId&acao=iniciar");
    $durante = sql("SELECT status FROM trens WHERE id = 3")[0]["status"];
    req("gestor", "PATCH", "viagens.php?id=$viagemId&acao=concluir");
    $depois = sql("SELECT status FROM trens WHERE id = 3")[0]["status"];
    $v = sql("SELECT status FROM viagens WHERE id = ?", [$viagemId])[0]["status"];
    return [$durante === "em_rota" && $depois === "disponivel" && $v === "concluida", "durante $durante, depois $depois, viagem $v"];
});
teste("Permissões", "Maquinista não conclui viagem de outro trem", "403", function () {
    $outra = sql("SELECT id FROM viagens WHERE trem_id <> 1 AND status IN ('em_andamento','atrasada') LIMIT 1")[0]["id"];
    $r = req("maquinista", "PATCH", "viagens.php?id=$outra&acao=concluir");
    return [$r["status"] === 403, (string) $r["status"]];
});

// ===================================================================== ALERTAS
teste("Alertas", "Maquinista gera alerta do próprio trem", "201 e alerta gravado com o trem 1", function () {
    $r = req("maquinista", "POST", "alertas.php", ["mensagem" => "Ruído estranho no truque dianteiro", "nivel" => "atencao", "trem_id" => 4]);
    $a = sql("SELECT trem_id, origem FROM alertas WHERE id = ?", [$r["json"]["id"] ?? 0]);
    return [$r["status"] === 201 && (int) $a[0]["trem_id"] === 1 && $a[0]["origem"] === "manual", $r["status"] . " - trem " . ($a[0]["trem_id"] ?? "?")];
});
teste("Alertas", "Cliente não gera alerta", "403", function () {
    $r = req("cliente", "POST", "alertas.php", ["mensagem" => "Teste do cliente", "nivel" => "normal"]);
    return [$r["status"] === 403, (string) $r["status"]];
});
teste("Alertas", "Gestor resolve alerta e filtro por gravidade funciona", "Alerta resolvido; filtro crítico só traz críticos", function () {
    $id = sql("SELECT id FROM alertas WHERE situacao = 'ativo' ORDER BY id LIMIT 1")[0]["id"];
    $r = req("gestor", "PATCH", "alertas.php?id=$id&acao=resolver");
    $s = sql("SELECT situacao FROM alertas WHERE id = ?", [$id])[0]["situacao"];
    $criticos = req("gestor", "GET", "alertas.php?nivel=critico")["json"];
    $outros = array_filter($criticos, fn($a) => $a["nivel"] !== "critico");
    return [$r["status"] === 200 && $s === "resolvido" && !$outros && $criticos, "situação $s, " . count($criticos) . " críticos, " . count($outros) . " de outro nível"];
});

// ===================================================================== DASHBOARD
teste("Dashboard", "Indicadores do painel batem com o banco", "Trens, alertas, sensores críticos, viagens e velocidade iguais ao SQL", function () {
    $p = req("gestor", "GET", "painel.php")["json"];
    $emRota = (int) sql("SELECT COUNT(*) AS t FROM trens WHERE status = 'em_rota'")[0]["t"];
    $criticos = (int) sql("SELECT COUNT(*) AS t FROM alertas WHERE situacao = 'ativo' AND nivel = 'critico'")[0]["t"];
    $viagens = (int) sql("SELECT COUNT(*) AS t FROM viagens WHERE status IN ('em_andamento','atrasada')")[0]["t"];
    $sensores = (int) sql("SELECT COUNT(*) AS t FROM sensores s WHERE s.status = 'ativo' AND (SELECT nivel FROM leituras l WHERE l.sensor_id = s.id ORDER BY data_hora DESC, id DESC LIMIT 1) = 'critico'")[0]["t"];
    $vel = sql("SELECT AVG(l.valor) AS m FROM leituras l JOIN sensores s ON s.id = l.sensor_id WHERE s.chave = 'velocidade' AND l.data_hora >= NOW() - INTERVAL 1 DAY")[0]["m"];
    $ok = $p["trens_por_situacao"]["em_rota"] === $emRota && $p["alertas_ativos"]["critico"] === $criticos
        && count($p["viagens_em_andamento"]) === $viagens && count($p["sensores_criticos"]) === $sensores
        && abs((float) $p["velocidade_media"]["media"] - round((float) $vel, 1)) < 0.2;
    return [$ok, "em rota {$p['trens_por_situacao']['em_rota']}/$emRota, críticos {$p['alertas_ativos']['critico']}/$criticos, viagens " . count($p["viagens_em_andamento"]) . "/$viagens, sensores " . count($p["sensores_criticos"]) . "/$sensores, velocidade {$p['velocidade_media']['media']}/" . round((float) $vel, 1)];
});

// ===================================================================== RELATÓRIOS E CSV
teste("Relatórios", "Os quatro relatórios respondem com dados do período", "200 e linhas nos 4 relatórios", function () {
    $ini = date("Y-m-d", strtotime("-30 days"));
    $fim = date("Y-m-d");
    $linhas = [];
    foreach (["desempenho", "energia", "eficiencia", "manutencao"] as $tipo) {
        $r = req("gestor", "GET", "relatorios.php?tipo=$tipo&data_inicial=$ini&data_final=$fim");
        $linhas[$tipo] = $r["status"] === 200 ? count($r["json"]["linhas"]) : -1;
    }
    return [min($linhas) > 0, json_encode($linhas)];
});
teste("Relatórios", "Período sem dados não gera erro e médias ficam vazias", "200 e médias nulas (sem divisão por zero)", function () {
    $r = req("gestor", "GET", "relatorios.php?tipo=desempenho&data_inicial=2000-01-01&data_final=2000-01-31");
    $medias = array_filter(array_column($r["json"]["linhas"], "velocidade_media"), fn($v) => $v !== null);
    $pont = array_filter(array_column($r["json"]["linhas"], "pontualidade"), fn($v) => $v !== null);
    return [$r["status"] === 200 && !$medias && !$pont, $r["status"] . " - " . count($medias) . " médias preenchidas"];
});
teste("Relatórios", "Maquinista vê relatório somente do próprio trem", "Apenas Trem 08", function () {
    $r = req("maquinista", "GET", "relatorios.php?tipo=desempenho&trem_id=4");
    $nomes = array_column($r["json"]["linhas"], "trem");
    return [$nomes === ["Trem 08"], implode(", ", $nomes)];
});
teste("CSV", "Exportação CSV sem HTML, com BOM UTF-8 e separador ;", "text/csv, BOM, acentos e nenhum '<'", function () {
    $r = req("gestor", "GET", "exportar.php?tipo=manutencao");
    $ok = str_contains(strtolower($r["cabecalho"]), "content-type: text/csv")
        && str_starts_with($r["corpo"], "\xEF\xBB\xBF")
        && !str_contains($r["corpo"], "<")
        && str_contains($r["corpo"], "Abertura;Trem;Tipo;Causa")
        && str_contains($r["corpo"], "Situação");
    $linhas = substr_count(trim($r["corpo"]), "\n");
    return [$ok, "status {$r['status']}, $linhas linhas de dados"];
});
teste("CSV", "CSV usa vírgula decimal e não tem separador de milhar", "Custo 18500,00", function () {
    $r = req("gestor", "GET", "exportar.php?tipo=manutencao");
    return [str_contains($r["corpo"], ";18500,00;"), str_contains($r["corpo"], "18500,00") ? "18500,00 encontrado" : "valor não encontrado"];
});
teste("CSV", "CSV sem sessão é bloqueado", "401", function () {
    return [req("anon3", "GET", "exportar.php?tipo=leituras")["status"] === 401, (string) req("anon3", "GET", "exportar.php?tipo=leituras")["status"]];
});

// ===================================================================== USUÁRIOS
teste("Usuários", "Gestor não pode desativar a própria conta", "409", function () {
    $r = req("gestor", "PATCH", "usuarios.php?id=1&acao=desativar");
    return [$r["status"] === 409, $r["status"] . " - " . $r["json"]["erro"]];
});
teste("Usuários", "Gestor desativado perde a sessão; sistema nunca fica sem gestor ativo", "Sessão do desativado cai (401); última gestora não se desativa nem se rebaixa (409/400)", function () {
    login("ana", "ana.oliveira@tr4.com");
    $a = req("ana", "PATCH", "usuarios.php?id=1&acao=desativar"); // Ana desativa o Gestor Demonstração
    $b = req("gestor", "GET", "usuario.php");                     // sessão do gestor desativado cai
    login("gestor", "gestor@tr4.com");                              // e não consegue entrar
    $c = req("ana", "PATCH", "usuarios.php?id=4&acao=desativar"); // Ana é a última gestora: bloqueado (também é ela mesma)
    // Reativa o gestor demonstração e tenta desativar a Ana por ele: Ana deixa de ser a última
    req("ana", "PATCH", "usuarios.php?id=1&acao=ativar");
    login("gestor", "gestor@tr4.com");
    $d = req("gestor", "PUT", "usuarios.php?id=1", ["nome" => "Gestor Demonstração", "email" => "gestor@tr4.com", "cargo" => "cliente"]);
    return [$a["status"] === 200 && $b["status"] === 401 && $c["status"] === 409 && $d["status"] === 400,
        "desativar outro gestor {$a['status']}, sessão dele {$b['status']}, desativar última {$c['status']}, rebaixar a si mesmo {$d['status']}"];
});
teste("Usuários", "Cadastrar usuário, alterar perfil e desativar", "Usuário maquinista vinculado ao trem e depois sem acesso", function () {
    $email = "teste" . time() . "@tr4.com";
    $r = req("gestor", "POST", "usuarios.php", ["nome" => "Usuário de Teste", "email" => $email, "cargo" => "cliente", "senha" => "segredo1"]);
    $id = $r["json"]["id"];
    $e = req("gestor", "PUT", "usuarios.php?id=$id", ["nome" => "Usuário de Teste", "email" => $email, "cargo" => "maquinista", "trem_id" => 3]);
    $antes = login("teste", $email, "segredo1")["status"];
    req("gestor", "PATCH", "usuarios.php?id=$id&acao=desativar");
    $sessao = req("teste", "GET", "usuario.php")["status"];
    $depois = login("teste2", $email, "segredo1")["status"];
    return [$e["json"]["cargo"] === "maquinista" && (int) $e["json"]["trem_id"] === 3 && $antes === 200 && $sessao === 401 && $depois === 403,
        "perfil {$e['json']['cargo']}, login antes $antes, sessão após desativar $sessao, login depois $depois"];
});
teste("Usuários", "Redefinir senha gera senha temporária com troca obrigatória", "Senha temporária funciona e trocar_senha = 1", function () {
    $r = req("gestor", "PATCH", "usuarios.php?id=9&acao=redefinir_senha");
    $l = login("maria", "maria.silva@tr4.com", $r["json"]["senha_temporaria"]);
    $t = req("maria", "PUT", "usuario.php?acao=senha", ["senha_atual" => $r["json"]["senha_temporaria"], "nova_senha" => "novaSenha1", "confirmacao" => "novaSenha1"]);
    $flag = sql("SELECT trocar_senha FROM usuarios WHERE id = 9")[0]["trocar_senha"];
    return [$l["status"] === 200 && (int) $l["json"]["trocar_senha"] === 1 && $t["status"] === 200 && (int) $flag === 0,
        "login temporário {$l['status']}, troca {$t['status']}, flag final $flag"];
});
teste("Usuários", "Pedido de recuperação não revela se o e-mail existe", "Mesma resposta para e-mail existente e inexistente", function () {
    $a = req("anon4", "POST", "senha.php", ["email" => "cliente@tr4.com"]);
    $b = req("anon4", "POST", "senha.php", ["email" => "ninguem@tr4.com"]);
    $alerta = sql("SELECT COUNT(*) AS t FROM alertas WHERE mensagem LIKE 'Pedido de redefinição de senha:%cliente@tr4.com%'")[0]["t"];
    return [$a["corpo"] === $b["corpo"] && $alerta > 0, "respostas iguais: " . ($a["corpo"] === $b["corpo"] ? "sim" : "não") . ", alerta ao gestor: $alerta"];
});

// ===================================================================== MANUTENÇÃO
teste("Manutenção", "Registrar e concluir manutenção atualiza a situação do trem", "Em manutenção ao registrar; disponível ao concluir", function () {
    $r = req("gestor", "POST", "manutencoes.php", ["trem_id" => 5, "tipo" => "preventiva", "causa" => "Troca de filtros"]);
    $durante = sql("SELECT status FROM trens WHERE id = 5")[0]["status"];
    $u = req("gestor", "PUT", "manutencoes.php?id=" . $r["json"]["id"], ["status" => "concluida", "custo" => "1500.50"]);
    $depois = sql("SELECT status FROM trens WHERE id = 5")[0]["status"];
    return [$r["status"] === 201 && $durante === "manutencao" && $u["status"] === 200 && $depois === "disponivel", "durante $durante, depois $depois"];
});

// ===================================================================== LOGOUT
teste("Logout", "Logout encerra a sessão", "Depois de sair, a API responde 401", function () {
    req("maquinista", "POST", "sair.php");
    $r = req("maquinista", "GET", "usuario.php");
    return [$r["status"] === 401, (string) $r["status"]];
});

// ===================================================================== RESULTADO
$aprovados = count(array_filter($resultados, fn($r) => $r[4] === "Aprovado"));
$total = count($resultados);
echo "\n$aprovados de $total testes aprovados.\n";

$data = date("d/m/Y H:i");
$md = "# Resultado dos testes automatizados da API\n\nExecutado em $data com `php testes/testes-api.php`. $aprovados de $total aprovados.\n\n";
$md .= "| Nº | Área | Teste | Resultado esperado | Resultado obtido | Status | Data |\n|---|---|---|---|---|---|---|\n";
foreach ($resultados as $i => $r) {
    $md .= "| " . ($i + 1) . " | " . implode(" | ", array_map(fn($x) => str_replace("|", "/", $x), $r)) . " | $data |\n";
}
file_put_contents(__DIR__ . "/resultado-testes-api.md", $md);

exit($aprovados === $total ? 0 : 1);
