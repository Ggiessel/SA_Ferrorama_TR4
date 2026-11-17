/* Meu perfil: dados da própria conta e troca de senha (o perfil de acesso só o gestor altera). */

function preencherPerfil(usuario) {
    ["nome", "email", "telefone", "nascimento", "ativo_desde"].forEach((campo) => {
        document.getElementById(campo).value = usuario[campo] ?? "";
    });
    document.getElementById("perfilAcesso").value = NOMES_CARGO[usuario.cargo];
    document.getElementById("tremPerfil").value = usuario.trem || "—";
}

iniciarPagina(async (usuario) => {
    preencherPerfil(usuario);
    const trocar = Number(usuario.trocar_senha) === 1;
    document.getElementById("avisoTrocar").hidden = !trocar;
    if (trocar) document.getElementById("senhaAtual").focus();

    document.getElementById("formPerfil").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        try {
            usuarioAtual = await api("usuario.php", "PUT", {
                nome: valor("nome"), email: valor("email"), telefone: valor("telefone"), nascimento: valor("nascimento")
            });
            document.querySelector(".app-usuario strong").textContent = usuarioAtual.nome;
            avisar("Perfil salvo.", "sucesso");
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.getElementById("btnDescartar").addEventListener("click", () => {
        limparErros();
        preencherPerfil(usuarioAtual);
        avisar("Alterações descartadas.");
    });

    document.getElementById("formSenha").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        try {
            await api("usuario.php?acao=senha", "PUT", {
                senha_atual: document.getElementById("senhaAtual").value,
                nova_senha: document.getElementById("novaSenha").value,
                confirmacao: document.getElementById("confirmacaoSenha").value
            });
            evento.target.reset();
            avisar("Senha alterada.", "sucesso");
            if (trocar) setTimeout(() => irParaMenu(), 900);
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
