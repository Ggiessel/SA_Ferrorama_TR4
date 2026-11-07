iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");

    preencherSelect(document.getElementById("rotaManutencao"), opcoes.rotas.map((rota) => ({ valor: rota.id, texto: rota.nome })));
    preencherSelect(document.getElementById("cargaManutencao"), ["Sem carga", ...opcoes.tiposCarga]);

    document.querySelector(".save-btn").addEventListener("click", async () => {
        limparErros();

        try {
            const resposta = await api("manutencoes.php", "POST", {
                codigo: valor("codigoTrem"),
                rota_id: valor("rotaManutencao"),
                carga: valor("cargaManutencao"),
                causa: valor("causaManutencao")
            });

            avisar(resposta.trem + " registrado na manutenção.", "sucesso");
            document.getElementById("codigoTrem").value = "";
            document.getElementById("causaManutencao").value = "";
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.querySelector(".pdf-btn").addEventListener("click", () => window.print());
});
