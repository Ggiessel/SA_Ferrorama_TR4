iniciarPagina(async () => {
    const select = document.querySelector(".rotas select");
    const rotas = await api("rotas.php");

    preencherSelect(select, rotas.map((rota) => ({ valor: rota.id, texto: rota.nome })));

    async function mostrarRota() {
        const rota = await api("rotas.php?id=" + select.value);

        document.querySelector(".grid-horarios-leitura").innerHTML = rota.horarios
            .map((horario) => `<div class="card-horario-div">${horario}</div>`)
            .join("");

        document.querySelector(".status-linhas-wrapper").innerHTML = rota.linhas
            .map((linha) => `
                <div class="bloco-linha">
                    <span class="label-linha">${escapar(linha.nome)}</span>
                    <div class="badge-status-leitura">${escapar(linha.status)}</div>
                </div>`)
            .join("");

        document.querySelector(".caixa-mensagem-alerta").innerHTML = `
            <span class="emoji-alerta">${rota.alerta ? "⚠️" : "✅"}</span>
            <p>${escapar(rota.alerta || "Nenhum problema nesta rota.")}</p>`;
    }

    select.addEventListener("change", () => mostrarRota().catch(mostrarErro));
    await mostrarRota();
});
