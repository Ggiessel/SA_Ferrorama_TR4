iniciarPagina(async () => {
    const lista = document.getElementById("listaCargas");
    const filtros = document.getElementById("filtroStatus");
    let filtro = "";

    filtros.innerHTML = `
        <button type="button" data-status="" aria-pressed="true">Todas</button>
        <button type="button" data-status="aguardando" aria-pressed="false">Aguardando</button>
        <button type="button" data-status="em_transito" aria-pressed="false">Em trânsito</button>
        <button type="button" data-status="entregue" aria-pressed="false">Entregue</button>`;

    filtros.querySelectorAll("button").forEach((botao) => {
        botao.addEventListener("click", () => {
            filtro = botao.dataset.status;
            filtros.querySelectorAll("button").forEach((b) => b.setAttribute("aria-pressed", b === botao));
            carregar();
        });
    });

    async function carregar() {
        const cargas = await api("cargas.php?status=" + filtro);

        if (cargas.length === 0) {
            lista.innerHTML = '<li class="estado-vazio">Nenhuma carga encontrada.</li>';
            return;
        }

        lista.innerHTML = cargas
            .map((carga) => `
                <li class="item-lista item-carga">
                    <div class="item-carga-topo">
                        <div>
                            <strong>${escapar(carga.tipo)} - ${carga.toneladas} t</strong>
                            <span class="item-detalhe">Destino: ${escapar(carga.destino)} - Partida: ${formatarData(carga.partida)}</span>
                        </div>
                        <span class="selo selo--${carga.status.replace("_", "-")}">${NOMES_STATUS[carga.status]}</span>
                    </div>
                    <div class="barra-progresso"><span style="width: ${carga.progresso}%"></span></div>
                    <span class="item-detalhe">${carga.progresso}% do trajeto</span>
                </li>`)
            .join("");
    }

    await carregar();

    setInterval(async () => {
        const resposta = await api("cargas.php?acao=avancar", "POST");
        resposta.entregues.forEach((mensagem) => avisar(mensagem, "sucesso"));
        await carregar();
    }, 3000);
});
