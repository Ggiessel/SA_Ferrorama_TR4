<?php
/*
  /funcionarios — cadastro de funcionários (só para o gestor)
  Usado por: funcionarios.html e perfil-funcionario.html
*/

declare(strict_types=1);

const SQL_FUNCIONARIO = '
    SELECT id, nome, cargo, ativo_desde AS ativoDesde, email, telefone, cpf,
           data_nascimento AS nascimento, endereco
      FROM funcionarios';

// GET /funcionarios?busca=silva
rota('GET', '/funcionarios', function () {
    exigirCargo('gestor');
    $busca = trim((string) ($_GET['busca'] ?? ''));

    if ($busca === '') {
        responder(consultar(SQL_FUNCIONARIO . ' ORDER BY nome'));
    }
    // A collation utf8mb4_unicode_ci faz "joao" achar "João"
    $termo = "%$busca%";
    responder(consultar(SQL_FUNCIONARIO . ' WHERE nome LIKE ? OR cargo LIKE ? ORDER BY nome', [$termo, $termo]));
});

// GET /funcionarios/3
rota('GET', '/funcionarios/(\d+)', function (int $id) {
    exigirCargo('gestor');
    $funcionario = consultarUm(SQL_FUNCIONARIO . ' WHERE id = ?', [$id]);
    if (!$funcionario) {
        throw new ErroApi(404, 'Funcionário não encontrado. Escolha alguém da lista.');
    }
    responder($funcionario);
});

// PUT /funcionarios/3   (datas em "AAAA-MM-DD")
rota('PUT', '/funcionarios/(\d+)', function (int $id) {
    exigirCargo('gestor');
    if (!consultarUm('SELECT id FROM funcionarios WHERE id = ?', [$id])) {
        throw new ErroApi(404, 'Funcionário não encontrado.');
    }

    $dados = corpo();
    $f = [
        'cargo' => texto($dados, 'cargo'),
        'nome' => texto($dados, 'nome'),
        'ativoDesde' => ouNulo($dados['ativoDesde'] ?? null),
        'email' => strtolower(texto($dados, 'email')),
        'telefone' => ouNulo($dados['telefone'] ?? null),
        'cpf' => ouNulo($dados['cpf'] ?? null),
        'nascimento' => ouNulo($dados['nascimento'] ?? null),
        'endereco' => ouNulo($dados['endereco'] ?? null),
    ];

    if ($f['cargo'] === '') {
        throw new ErroApi(400, 'Informe o cargo.', 'cargo');
    }
    if (mb_strlen($f['nome']) < 3) {
        throw new ErroApi(400, 'Informe o nome completo.', 'nome');
    }
    if (!$f['ativoDesde'] || !dataNoPassado($f['ativoDesde'])) {
        throw new ErroApi(400, 'Use uma data válida, até hoje (dd/mm/aaaa).', 'ativoDesde');
    }
    if (!validarEmail($f['email'])) {
        throw new ErroApi(400, 'Informe um e-mail válido.', 'email');
    }
    if ($f['telefone'] && !validarTelefone($f['telefone'])) {
        throw new ErroApi(400, 'Telefone com DDD: (47) 99999-9999.', 'telefone');
    }
    if ($f['cpf'] && !validarCpf($f['cpf'])) {
        throw new ErroApi(400, 'CPF inválido. Confira os números.', 'cpf');
    }
    if ($f['nascimento'] && !dataNoPassado($f['nascimento'])) {
        throw new ErroApi(400, 'Use uma data válida, até hoje (dd/mm/aaaa).', 'nascimento');
    }

    try {
        executar(
            'UPDATE funcionarios
                SET cargo = ?, nome = ?, ativo_desde = ?, email = ?, telefone = ?,
                    cpf = ?, data_nascimento = ?, endereco = ?
              WHERE id = ?',
            [$f['cargo'], $f['nome'], $f['ativoDesde'], $f['email'], $f['telefone'],
                $f['cpf'], $f['nascimento'], $f['endereco'], $id]
        );
    } catch (PDOException $erro) {
        if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
            $ehCpf = str_contains($erro->getMessage(), 'cpf');
            throw new ErroApi(409,
                $ehCpf ? 'Esse CPF já é de outro funcionário.' : 'Esse e-mail já é de outro funcionário.',
                $ehCpf ? 'cpf' : 'email');
        }
        throw $erro;
    }

    responder(consultarUm(SQL_FUNCIONARIO . ' WHERE id = ?', [$id]));
});
