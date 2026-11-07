iniciarPagina(() => {
    document.getElementById("formLogin").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        try {
            const usuario = await api("login.php", "POST", {
                email: valor("emailLogin"),
                senha: valor("senhaLogin")
            });
            irParaMenu(usuario);
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.querySelectorAll(".btn-continuar").forEach((botao) => {
        botao.addEventListener("click", () => {
            avisar("Entrar com " + botao.dataset.provedor + " ainda não está disponível.");
        });
    });
});
