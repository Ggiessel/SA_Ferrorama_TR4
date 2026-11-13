iniciarPagina(async () => {
    const sensores = await api("sensores.php");
    const ativos = sensores.filter((sensor) => sensor.status === "ativo");
    const select = document.getElementById("sensor");

    preencherSelect(select, ativos.map((sensor) => ({ valor: sensor.id, texto: sensor.tipo + " (" + sensor.grandeza + ") - " + sensor.trem })));

    document.querySelector(".btn-remover").addEventListener("click", async () => {
        if (!select.value || !confirm("Remover este sensor?")) {
            return;
        }

        try {
            await api("sensores.php?id=" + select.value, "PUT");
            location.href = "gestao-sensores.html";
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
