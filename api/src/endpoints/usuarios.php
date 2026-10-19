<?php
/*
  /usuarios — dados de quem está logado
  Usado por: selecao-usuario.html e perfil-pessoal.html
*/

declare(strict_types=1);

// PUT /usuarios/eu/cargo   { cargo }
rota('PUT', '/usuarios/eu/cargo', function () {
    $usuario = exigirLogin();
    $cargo = texto(corpo(), 'cargo');

    if (!in_array($cargo, ['gestor', 'maquinista', 'cliente'], true)) {
        throw new ErroApi(400, 'Cargo inválido.');
    }

    executar('UPDATE usuarios SET cargo = ? WHERE id = ?', [$cargo, $usuario['id']]);
    $usuario['cargo'] = $cargo;
    responder(usuarioPublico($usuario));
});

// PUT /usuarios/eu   { nome, nascimento, email, telefone }   (datas em "AAAA-MM-DD")
rota('PUT', '/usuarios/eu', function () {
    $usuario = exigirLogin();
    $dados = corpo();

    $nome = texto($dados, 'nome');
    $email = strtolower(texto($dados, 'email'));
    $nascimento = ouNulo($dados['nascimento'] ?? null);
    $telefone = ouNulo($dados['telefone'] ?? null);

    if (mb_strlen($nome) < 3) {
        throw new ErroApi(400, 'Informe seu nome completo.', 'nome');
    }
    if ($nascimento && !dataNoPassado($nascimento)) {
        throw new ErroApi(400, 'Use uma data válida, até hoje (dd/mm/aaaa).', 'nascimento');
    }
    if (!validarEmail($email)) {
        throw new ErroApi(400, 'Informe um e-mail válido.', 'email');
    }
    if ($telefone && !validarTelefone($telefone)) {
        throw new ErroApi(400, 'Telefone com DDD: (47) 99999-9999.', 'telefone');
    }
    if (consultarUm('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $usuario['id']])) {
        throw new ErroApi(409, 'Esse e-mail já é usado por outra conta.', 'email');
    }

    executar(
        'UPDATE usuarios SET nome = ?, email = ?, data_nascimento = ?, telefone = ? WHERE id = ?',
        [$nome, $email, $nascimento, $telefone, $usuario['id']]
    );

    responder(usuarioPublico(consultarUm('SELECT * FROM usuarios WHERE id = ?', [$usuario['id']])));
});
