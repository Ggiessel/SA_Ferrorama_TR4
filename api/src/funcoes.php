<?php
/*
  Funções de apoio: respostas JSON, erros, roteamento e validações.
*/

declare(strict_types=1);

/* ============================================================
   ERROS
   ============================================================ */

/**
 * Erro com código HTTP. O index.php transforma em:
 *   { "erro": "mensagem", "campo": "email" }
 * campo (opcional) = id do campo do formulário que deve ficar vermelho.
 */
class ErroApi extends Exception
{
    public int $status;
    public ?string $campo;

    public function __construct(int $status, string $mensagem, ?string $campo = null)
    {
        parent::__construct($mensagem);
        $this->status = $status;
        $this->campo = $campo;
    }
}

/* ============================================================
   RESPOSTAS
   ============================================================ */

/** Envia a resposta em JSON e encerra o script. */
function responder($dados, int $status = 200): void
{
    http_response_code($status);
    if ($status === 204) {
        exit;
    }
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Lê o corpo do pedido (JSON enviado pelo site) como array. */
function corpo(): array
{
    static $dados = null;
    if ($dados === null) {
        $texto = file_get_contents('php://input');
        $dados = $texto === '' || $texto === false ? [] : json_decode($texto, true);
        if (!is_array($dados)) {
            throw new ErroApi(400, 'Dados enviados em formato inválido.');
        }
    }
    return $dados;
}

/* ============================================================
   ROTEAMENTO
   Cada arquivo em src/endpoints/ registra seus endereços com rota().
   ============================================================ */

$GLOBALS['ROTAS'] = [];

/**
 * Registra um endpoint.
 *   rota('GET', '/rotas/(\d+)', function (int $id) { ... });
 * O que estiver entre parênteses no padrão vira parâmetro da função.
 */
function rota(string $metodo, string $padrao, callable $funcao): void
{
    $GLOBALS['ROTAS'][] = [$metodo, '#^' . $padrao . '$#', $funcao];
}

/** Descobre qual endpoint foi pedido e chama a função dele. */
function despachar(): void
{
    // Endereço depois de index.php: /api/index.php/rotas/2 -> "/rotas/2"
    $caminho = $_SERVER['PATH_INFO'] ?? ($_GET['rota'] ?? '/');
    $caminho = '/' . trim((string) $caminho, '/');
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $metodoErrado = false;
    foreach ($GLOBALS['ROTAS'] as [$metodoRota, $regex, $funcao]) {
        if (preg_match($regex, $caminho, $partes)) {
            if ($metodoRota !== $metodo) {
                $metodoErrado = true;
                continue;
            }
            array_shift($partes);
            $parametros = array_map(fn ($p) => ctype_digit($p) ? (int) $p : $p, $partes);
            $funcao(...$parametros);
            return;
        }
    }

    if ($metodoErrado) {
        throw new ErroApi(405, "Método $metodo não permitido em $caminho.");
    }
    throw new ErroApi(404, "Endpoint não encontrado: $metodo $caminho");
}

/* ============================================================
   VALIDAÇÕES (as mesmas do js/global.js do site)
   O servidor valida de novo porque não dá para confiar no navegador.
   ============================================================ */

function soNumeros($texto): string
{
    return preg_replace('/\D/', '', (string) $texto);
}

function validarEmail($texto): bool
{
    return (bool) preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', trim((string) $texto));
}

function validarTelefone($texto): bool
{
    return in_array(strlen(soNumeros($texto)), [10, 11], true);
}

function validarCpf($texto): bool
{
    $n = soNumeros($texto);
    if (strlen($n) !== 11 || preg_match('/^(\d)\1{10}$/', $n)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $n[$i] * ($t + 1 - $i);
        }
        if ((($soma * 10) % 11) % 10 !== (int) $n[$t]) {
            return false;
        }
    }
    return true;
}

/** "2026-10-19" e a data existe de verdade */
function validarDataISO($texto): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $texto, $p)) {
        return false;
    }
    return checkdate((int) $p[2], (int) $p[3], (int) $p[1]);
}

function dataNoPassado($texto): bool
{
    return validarDataISO($texto) && $texto <= hojeISO();
}

function hojeISO(): string
{
    return date('Y-m-d');
}

/** Texto vazio vira null (para colunas opcionais do banco). */
function ouNulo($valor): ?string
{
    $texto = trim((string) ($valor ?? ''));
    return $texto === '' ? null : $texto;
}

/** Texto de um campo do corpo, sem espaços nas pontas. */
function texto(array $dados, string $chave): string
{
    return trim((string) ($dados[$chave] ?? ''));
}

/** Dados do usuário que podem ir para o site (nunca a senha). */
function usuarioPublico(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'nome' => $u['nome'],
        'email' => $u['email'],
        'cargo' => $u['cargo'],
        'nascimento' => $u['data_nascimento'],
        'ativoDesde' => $u['ativo_desde'],
        'telefone' => $u['telefone'],
    ];
}

const STATUS_TEXTO = [
    'aguardando' => 'Aguardando',
    'em_transito' => 'Em trânsito',
    'entregue' => 'Entregue',
    'em_espera' => 'Em espera',
    'ja_partiu' => 'Já partiu',
    'atrasado' => 'Atrasado',
    'em_rota' => 'Em rota',
    'disponivel' => 'Disponível',
    'manutencao' => 'Em manutenção',
    'ativo' => 'Ativo',
    'instalacao' => 'Instalação',
    'remocao' => 'Remoção',
];
