/* Tela inicial de cada perfil: atalhos dos módulos permitidos e um resumo com dados reais. */

const DESCRICOES = {
    "gestao-geral": "Indicadores da operação em tempo real",
    trens: "Frota, situação operacional e cadastro",
    sensores: "Sensores instalados e última leitura",
    leituras: "Histórico de leituras com filtros",
    simulador: "Gerar leituras para os sensores",
    rotas: "Rotas, horários e viagens",
    cargas: "Cargas em transporte",
    manutencao: "Registro e acompanhamento",
    alertas: "Alertas por gravidade",
    relatorios: "Desempenho, energia, eficiência e manutenção",
    usuarios: "Contas, perfis e senhas",
    "perfil-pessoal": "Seus dados e sua senha"
};

iniciarPagina(async (usuario) => {
    document.getElementById("boasVindas").textContent =
        `Olá, ${usuario.nome.split(" ")[0]}! Escolha um módulo abaixo ou use o menu lateral.`;

    document.getElementById("modulos").innerHTML = ITENS_MENU
        .filter(([pagina, , , perfis]) => pagina !== "inicio" && perfis.includes(usuario.cargo))
        .map(([pagina, rotulo, icone]) => `
            <a class="modulo" href="${pagina}.html">
                <i class="bi ${icone}" aria-hidden="true"></i>
                <strong>${rotulo}</strong>
                <span>${DESCRICOES[pagina] || ""}</span>
            </a>`)
        .join("");

    const info = document.getElementById("infoInicio");
    const alertas = await api("alertas.php?situacao=ativo");
    const listaAlertas = alertas.slice(0, 5).map((alerta) => `
        <li><span>${seloNivel(alerta.nivel)} ${escapar(alerta.mensagem)}</span><small>${formatarDataHora(alerta.data)}</small></li>`).join("");

    let blocoRotas = "";
    if (usuario.cargo === "cliente") {
        const viagens = await api("viagens.php?data_inicial=" + hoje() + "&data_final=" + hoje());
        const ativas = await api("viagens.php?status=ativas");
        const todas = [...ativas, ...viagens.filter((v) => !ativas.some((a) => a.id === v.id))];
        blocoRotas = `
            <section class="cartao">
                <h2><i class="bi bi-signpost-split"></i> Viagens de hoje</h2>
                <ul class="lista-simples">${todas.map((v) => `
                    <li><span><strong>${escapar(v.rota)}</strong> · ${escapar(v.origem)} → ${escapar(v.destino)}</span>
                    <span>${formatarDataHora(v.partida_prevista)} ${selo(v.status)}</span></li>`).join("") || "<li>Nenhuma viagem hoje.</li>"}</ul>
            </section>`;
    }

    info.innerHTML = blocoRotas + `
        <section class="cartao">
            <h2><i class="bi bi-bell"></i> ${usuario.cargo === "cliente" ? "Avisos" : "Alertas ativos"}</h2>
            <ul class="lista-simples">${listaAlertas || "<li>Nenhum alerta ativo.</li>"}</ul>
            <a class="btn-secundario btn-pequeno mt-3" href="alertas.html">Ver todos</a>
        </section>`;
});
