/* Alertas: lista por gravidade (crítico em vermelho), filtros reais, criação manual e resolução. */

const ICONES_NIVEL = { normal: "bi-info-circle-fill", atencao: "bi-exclamation-triangle-fill", critico: "bi-x-octagon-fill" };
const ORIGENS = { sensor: "Sensor", manual: "Manual", sistema: "Sistema" };

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const cliente = usuario.cargo === "cliente";
    const filtros = document.getElementById("filtrosAlertas");
    const lista = document.getElementById("listaAlertas");

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));
    document.querySelectorAll(".nao-cliente").forEach((el) => (el.hidden = cliente));
    if (cliente) {
        document.querySelector(".app-topo h1").textContent = "Avisos";
        document.getElementById("descricaoAlertas").textContent = "Avisos públicos sobre rotas, viagens e cargas.";
    }

    if (!cliente) {
        const opcoes = await api("opcoes.php");
        preencherSelect(document.getElementById("filtroTremAlerta"), opcoesTrens(opcoes.trens), "Todos os trens");
        preencherSelect(document.getElementById("tremAlerta"), opcoesTrens(opcoes.trens), gestor ? "Sem trem (geral)" : "");
    }

    async function carregar() {
        const alertas = await api("alertas.php?" + lerFiltros(filtros));
        const contagem = { critico: 0, atencao: 0, normal: 0 };
        alertas.forEach((a) => contagem[a.nivel]++);
        document.getElementById("resumoAlertas").innerHTML = `
            <div class="item"><strong class="valor-critico">${contagem.critico}</strong><span>críticos</span></div>
            <div class="item"><strong class="valor-atencao">${contagem.atencao}</strong><span>em atenção</span></div>
            <div class="item"><strong>${contagem.normal}</strong><span>normais</span></div>`;

        lista.innerHTML = alertas.map((a) => `
            <article class="alerta-v2 alerta-v2--${a.nivel} ${a.situacao === "resolvido" ? "resolvido" : ""}">
                <i class="bi ${ICONES_NIVEL[a.nivel]}" aria-hidden="true"></i>
                <div class="alerta-corpo">
                    <p class="alerta-texto">${escapar(a.mensagem)}</p>
                    <div class="alerta-meta">
                        ${seloNivel(a.nivel)}
                        ${a.situacao === "resolvido" ? selo("resolvido") : selo("ativo")}
                        <span><i class="bi bi-clock"></i> ${formatarDataHora(a.data)}</span>
                        ${a.trem ? `<span><i class="bi bi-train-front"></i> ${escapar(a.trem)}</span>` : ""}
                        ${!cliente ? `<span><i class="bi bi-tag"></i> ${ORIGENS[a.origem]}${a.sensor ? " · " + escapar(a.sensor) : ""}</span>` : ""}
                        ${a.criado_por && !cliente ? `<span><i class="bi bi-person"></i> ${escapar(a.criado_por)}</span>` : ""}
                    </div>
                </div>
                ${gestor ? `<div class="acoes-linha">
                    ${a.situacao === "ativo"
                        ? `<button type="button" class="btn-secundario btn-pequeno" data-acao="resolver" data-id="${a.id}"><i class="bi bi-check2"></i> Resolver</button>`
                        : `<button type="button" class="btn-secundario btn-pequeno" data-acao="reabrir" data-id="${a.id}">Reabrir</button>`}
                    <button type="button" class="btn-icone perigo" data-apagar="${a.id}" title="Apagar" aria-label="Apagar alerta"><i class="bi bi-trash3"></i></button>
                </div>` : ""}
            </article>`).join("") || '<p class="estado-vazio">Nenhum alerta encontrado.</p>';
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });
    document.getElementById("buscaAlerta").addEventListener("input", () => carregar().catch(mostrarErro));

    lista.addEventListener("click", async (evento) => {
        const botao = evento.target.closest("button");
        if (!botao) return;
        try {
            if (botao.dataset.acao) {
                await api(`alertas.php?id=${botao.dataset.id}&acao=${botao.dataset.acao}`, "PATCH");
                avisar(botao.dataset.acao === "resolver" ? "Alerta resolvido." : "Alerta reaberto.", "sucesso");
            }
            if (botao.dataset.apagar) {
                if (!confirm("Apagar este alerta? Esta ação não pode ser desfeita.")) return;
                await api("alertas.php?id=" + botao.dataset.apagar, "DELETE");
                avisar("Alerta apagado.", "sucesso");
            }
            await carregar();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    if (!cliente) {
        const botaoNovo = document.getElementById("btnNovoAlerta");
        const abrir = () => { document.getElementById("formAlerta").reset(); abrirModal("modalAlerta"); };
        botaoNovo.addEventListener("click", abrir);
        if (!gestor && !usuario.trem_id) botaoNovo.disabled = true;
        if (new URLSearchParams(location.search).get("novo") && !botaoNovo.disabled) abrir();

        document.getElementById("formAlerta").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            try {
                await comCarregamento(document.getElementById("btnSalvarAlerta"), () => api("alertas.php", "POST", {
                    nivel: valor("nivelAlerta"), trem_id: valor("tremAlerta"), mensagem: valor("mensagemAlerta"),
                    publico: gestor && document.getElementById("publicoAlerta").checked
                }));
                fecharModal("modalAlerta");
                avisar("Alerta gerado.", "sucesso");
                await carregar();
            } catch (erro) {
                mostrarErro(erro);
            }
        });
    }

    await carregar();
});
