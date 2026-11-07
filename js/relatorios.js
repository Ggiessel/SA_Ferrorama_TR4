iniciarPagina(async () => {
    const resumo = await api("relatorios.php");
    const cargas = await api("cargas.php");
    const manutencoes = await api("manutencoes.php");

    document.getElementById("resumoRelatorio").innerHTML = `
        <div class="item-resumo"><strong>${resumo.cargas}</strong><span>cargas cadastradas</span></div>
        <div class="item-resumo"><strong>${resumo.em_transito}</strong><span>cargas em trânsito</span></div>
        <div class="item-resumo"><strong>${resumo.entregues}</strong><span>cargas entregues</span></div>
        <div class="item-resumo"><strong>${resumo.trens_manutencao}</strong><span>trens em manutenção</span></div>
        <div class="item-resumo"><strong>${resumo.sensores_ativos}</strong><span>sensores ativos</span></div>`;

    document.querySelector("#tabelaCargas tbody").innerHTML = cargas
        .map((carga) => `
            <tr>
                <td>${escapar(carga.tipo)}</td>
                <td>${carga.toneladas} t</td>
                <td>${escapar(carga.destino)}</td>
                <td>${formatarData(carga.partida)}</td>
                <td>${NOMES_STATUS[carga.status]}</td>
            </tr>`)
        .join("");

    document.querySelector("#tabelaManutencoes tbody").innerHTML = manutencoes
        .map((manutencao) => `
            <tr>
                <td>${manutencao.codigo}</td>
                <td>${escapar(manutencao.rota)}</td>
                <td>${escapar(manutencao.carga)}</td>
                <td>${escapar(manutencao.causa)}</td>
                <td>${formatarData(manutencao.data)}</td>
            </tr>`)
        .join("") || '<tr><td colspan="5">Nenhuma manutenção registrada.</td></tr>';

    document.getElementById("btnImprimir").addEventListener("click", () => window.print());
});
