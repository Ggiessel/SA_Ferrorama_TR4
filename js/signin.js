iniciarPagina(() => {
    document.getElementById("formCadastro").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        try {
            const usuario = await api("cadastro.php", "POST", {
                nome: valor("nomeCadastro"),
                email: valor("emailCadastro"),
                senha: valor("senhaCadastro"),
                confirmacao: valor("confirmacaoCadastro")
            });
            irParaMenu(usuario);
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
