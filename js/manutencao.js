/* Manutenção: gestor registra e atualiza; maquinista registra e consulta as do próprio trem. */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const opcoes = await api("opcoes.php");
    const filtros = document.getElementById("filtrosManutencao");
    const tabela = document.getElementById("tabelaManutencoes");
    let manutencoes = [];
    let atualizandoId = null;

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));
    if (!gestor) document.getElementById("descricaoManutencao").textContent = "Manutenções do seu trem.";

    preencherSelect(document.getElementById("filtroTremManutencao"), opcoesTrens(opcoes.trens), "Todos os trens");
    preencherSelect(document.getElementById("codigoTrem"), opcoes.trens.map((t) => ({ valor: t.codigo, texto: `${t.nome} (${t.codigo})` })), gestor ? "Escolha o trem" : "");
    preencherSelect(document.getElementById("rotaManutencao"), opcoes.rotas.map((r) => ({ valor: r.id, texto: r.nome })), "Sem rota");

    async function carregar() {
        manutencoes = await api("manutencoes.php?" + lerFiltros(filtros));
        preencherTabela(tabela, manutencoes.map((m) => `
            <tr>
                <td>${formatarData(m.data)}</td>
                <td><strong>${escapar(m.trem)}</strong></td>
                <td>${escapar(m.causa)}<br>${selo(m.tipo)}</td>
                <td>${escapar(m.rota || "—")}</td>
                <td>${selo(m.status)}</td>
                <td>${moeda(m.custo)}</td>
                <td>${formatarData(m.data_conclusao)}</td>
                <td>${escapar(m.responsavel || "—")}</td>
                <td><div class="acoes-linha">${gestor ? `<button type="button" class="btn-secundario btn-pequeno" data-atualizar="${m.id}"><i class="bi bi-pencil"></i> Atualizar</button>` : "—"}</div></td>
            </tr>`).join(""), "Nenhuma manutenção encontrada.");
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

    document.getElementById("btnNovaManutencao").addEventListener("click", () => {
        document.getElementById("formManutencao").reset();
        abrirModal("modalManutencao");
    });

    document.getElementById("formManutencao").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        try {
            const r = await comCarregamento(document.getElementById("btnSalvarManutencao"), () => api("manutencoes.php", "POST", {
                codigo: valor("codigoTrem"), tipo: valor("tipoManutencao"), rota_id: valor("rotaManutencao"),
                carga: valor("cargaManutencao"), causa: valor("causaManutencao")
            }));
            fecharModal("modalManutencao");
            avisar(`Manutenção registrada. O ${r.trem} está em manutenção.`, "sucesso");
            await carregar();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    tabela.addEventListener("click", (evento) => {
        const botao = evento.target.closest("[data-atualizar]");
        if (!botao) return;
        const m = manutencoes.find((x) => x.id == botao.dataset.atualizar);
        atualizandoId = m.id;
        document.getElementById("tituloModalAtualizar").textContent = `Manutenção do ${m.trem}`;
        document.getElementById("statusManutencao").value = m.status;
        document.getElementById("custoManutencao").value = m.custo ?? "";
        document.getElementById("conclusaoManutencao").value = m.data_conclusao || "";
        document.getElementById("conclusaoManutencao").min = m.data;
        abrirModal("modalAtualizar");
    });

    document.getElementById("formAtualizar").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        try {
            await api("manutencoes.php?id=" + atualizandoId, "PUT", {
                status: valor("statusManutencao"), custo: valor("custoManutencao"), data_conclusao: valor("conclusaoManutencao")
            });
            fecharModal("modalAtualizar");
            avisar("Manutenção atualizada.", "sucesso");
            await carregar();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    await carregar();
});
