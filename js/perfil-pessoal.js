const camposPerfil = ["nome", "nascimento", "ativo_desde", "email", "telefone"];

function preencherPerfil(usuario) {
    camposPerfil.forEach((campo) => {
        document.getElementById(campo).value = usuario[campo] ?? "";
    });
}

async function salvar() {
    limparErros();

    try {
        usuarioAtual = await api("usuario.php", "PUT", {
            nome: valor("nome"),
            nascimento: valor("nascimento"),
            email: valor("email"),
            telefone: valor("telefone")
        });
        avisar("Perfil salvo.", "sucesso");
    } catch (erro) {
        mostrarErro(erro);
    }
}

function descartar() {
    limparErros();
    preencherPerfil(usuarioAtual);
    avisar("Alterações descartadas.");
}

iniciarPagina(preencherPerfil);
