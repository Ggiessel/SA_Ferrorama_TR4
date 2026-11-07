iniciarPagina(async () => {
    const lista = document.getElementById("listaFuncionarios");
    const busca = document.getElementById("buscaFuncionario");

    async function carregar() {
        const funcionarios = await api("funcionarios.php?busca=" + encodeURIComponent(busca.value));

        if (funcionarios.length === 0) {
            lista.innerHTML = '<p class="estado-vazio">Nenhum funcionário encontrado.</p>';
            return;
        }

        lista.innerHTML = funcionarios
            .map((funcionario) => `
                <a class="funcionarios" href="perfil-funcionario.html?id=${funcionario.id}">
                    <span>${escapar(funcionario.nome)}</span>
                    <span class="item-detalhe">${escapar(funcionario.cargo)}</span>
                </a>`)
            .join("");
    }

    busca.addEventListener("input", carregar);
    document.getElementById("btnPesquisar").addEventListener("click", carregar);
    await carregar();
});
