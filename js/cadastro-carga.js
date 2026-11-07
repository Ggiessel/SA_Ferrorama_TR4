iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const tipo = document.getElementById("tipoCarga");
    const tamanho = document.getElementById("tamanhoCarga");
    const destino = document.getElementById("destinoCarga");
    const partida = document.getElementById("dataPartida");

    preencherSelect(tipo, opcoes.tiposCarga, "Escolha a carga");
    preencherSelect(tamanho, opcoes.tamanhosCarga.map((t) => ({ valor: t, texto: t + " toneladas" })), "Escolha o tamanho");
    preencherSelect(destino, opcoes.destinos, "Escolha o destino");
    partida.min = hoje();
    partida.value = hoje();

    document.querySelector(".btn-salvar").addEventListener("click", async () => {
        limparErros();

        try {
            await api("cargas.php", "POST", {
                tipo: tipo.value,
                toneladas: tamanho.value,
                destino: destino.value,
                partida: partida.value
            });

            avisar("Carga cadastrada! Acompanhe em Monitoramento de Cargas.", "sucesso");
            tipo.value = "";
            tamanho.value = "";
            destino.value = "";
            partida.value = hoje();
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
