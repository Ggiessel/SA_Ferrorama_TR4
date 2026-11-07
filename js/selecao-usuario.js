iniciarPagina(() => {
    document.querySelectorAll("button[data-cargo]").forEach((botao) => {
        botao.addEventListener("click", async () => {
            try {
                const usuario = await api("usuario.php", "PUT", { cargo: botao.dataset.cargo });
                irParaMenu(usuario);
            } catch (erro) {
                mostrarErro(erro);
            }
        });
    });
});
