const camposFuncionario = ["cargo", "nome", "ativo_desde", "email", "telefone", "cpf", "nascimento", "endereco"];
const idFuncionario = new URLSearchParams(location.search).get("id");

async function carregarFuncionario() {
    const funcionario = await api("funcionarios.php?id=" + idFuncionario);

    camposFuncionario.forEach((campo) => {
        document.getElementById(campo).value = funcionario[campo] ?? "";
    });
}

async function salvar() {
    limparErros();

    const dados = {};
    camposFuncionario.forEach((campo) => {
        dados[campo] = valor(campo);
    });

    try {
        await api("funcionarios.php?id=" + idFuncionario, "PUT", dados);
        location.href = "funcionarios.html";
    } catch (erro) {
        mostrarErro(erro);
    }
}

async function descartar() {
    limparErros();
    await carregarFuncionario();
    avisar("Alterações descartadas.");
}

iniciarPagina(carregarFuncionario);
