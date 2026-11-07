iniciarPagina(() => {
    document.getElementById("formCadastro").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        try {
            await api("cadastro.php", "POST", {
                nome: valor("nomeCadastro"),
                email: valor("emailCadastro"),
                senha: valor("senhaCadastro")
            });
            location.href = "selecao-usuario.html";
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
