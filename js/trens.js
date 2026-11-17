/* Trens: gestor lista, cadastra, edita, altera a situação e exclui. Maquinista vê apenas o próprio trem. */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const tabela = document.getElementById("tabelaTrens");
    const filtros = document.getElementById("filtrosTrens");
    let trens = [];
    let editandoId = null;
    let situacaoId = null;

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));
    document.querySelectorAll(".somente-maquinista").forEach((el) => (el.hidden = gestor));
    if (!gestor) {
        tabela.querySelector(".somente-gestor-col").remove();
        document.getElementById("descricaoTrens").textContent = usuario.trem_id
            ? "Informações do trem que você conduz."
            : "Você ainda não está vinculado a um trem. Procure o gestor.";
    }

    const opcoes = await api("opcoes.php");
    const rotasAtivas = opcoes.rotas.filter((r) => Number(r.ativa)).map((r) => ({ valor: r.id, texto: r.nome }));
    preencherSelect(document.getElementById("rotaTrem"), rotasAtivas, "Sem rota");
    preencherSelect(document.getElementById("rotaSituacao"), rotasAtivas, "Sem rota");

    function sincronizarRota(selectStatus, selectRota) {
        selectRota.disabled = selectStatus.value !== "em_rota";
        if (selectRota.disabled) selectRota.value = "";
    }
    const statusTrem = document.getElementById("statusTrem");
    const situacaoNova = document.getElementById("situacaoNova");
    statusTrem.addEventListener("change", () => sincronizarRota(statusTrem, document.getElementById("rotaTrem")));
    situacaoNova.addEventListener("change", () => sincronizarRota(situacaoNova, document.getElementById("rotaSituacao")));

    async function carregar() {
        trens = await api("trens.php?" + lerFiltros(filtros));
        preencherTabela(tabela, trens.map((t) => `
            <tr>
                <td>${t.codigo}</td>
                <td><strong>${escapar(t.nome)}</strong></td>
                <td>${escapar(t.modelo)}<br><small class="text-secondary">${t.ano_fabricacao}</small></td>
                <td>${numero(t.capacidade_toneladas, 0)} t</td>
                <td>${numero(t.consumo, 1)} km/L</td>
                <td>${selo(t.status)}</td>
                <td>${escapar(t.rota || "—")}</td>
                <td>${escapar(t.maquinista || "—")}</td>
                <td><a href="sensores.html?trem=${t.id}">${t.sensores_ativos}</a></td>
                ${gestor ? `<td><div class="acoes-linha">
                    <button type="button" class="btn-icone" data-situacao="${t.id}" title="Alterar situação" aria-label="Alterar situação do ${escapar(t.nome)}"><i class="bi bi-arrow-repeat"></i></button>
                    <button type="button" class="btn-icone" data-editar="${t.id}" title="Editar" aria-label="Editar ${escapar(t.nome)}"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn-icone perigo" data-excluir="${t.id}" title="Excluir" aria-label="Excluir ${escapar(t.nome)}"><i class="bi bi-trash3"></i></button>
                </div></td>` : ""}
            </tr>`).join(""), "Nenhum trem encontrado.");
    }

    if (gestor) {
        filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

        document.getElementById("btnNovoTrem").addEventListener("click", () => {
            editandoId = null;
            document.getElementById("formTrem").reset();
            document.getElementById("tituloModalTrem").textContent = "Novo trem";
            sincronizarRota(statusTrem, document.getElementById("rotaTrem"));
            abrirModal("modalTrem");
        });

        tabela.addEventListener("click", async (evento) => {
            const botao = evento.target.closest("button");
            if (!botao) return;

            if (botao.dataset.editar) {
                const t = trens.find((x) => x.id == botao.dataset.editar);
                editandoId = t.id;
                document.getElementById("tituloModalTrem").textContent = "Editar " + t.nome;
                document.getElementById("codigoTrem").value = t.codigo;
                document.getElementById("nomeTrem").value = t.nome;
                document.getElementById("modeloTrem").value = t.modelo;
                document.getElementById("anoTrem").value = t.ano_fabricacao;
                document.getElementById("capacidadeTrem").value = Number(t.capacidade_toneladas);
                document.getElementById("consumoTrem").value = Number(t.consumo);
                statusTrem.value = t.status;
                sincronizarRota(statusTrem, document.getElementById("rotaTrem"));
                document.getElementById("rotaTrem").value = t.rota_id || "";
                abrirModal("modalTrem");
            }

            if (botao.dataset.situacao) {
                const t = trens.find((x) => x.id == botao.dataset.situacao);
                situacaoId = t.id;
                document.getElementById("tituloModalSituacao").textContent = "Situação do " + t.nome;
                situacaoNova.value = t.status;
                sincronizarRota(situacaoNova, document.getElementById("rotaSituacao"));
                document.getElementById("rotaSituacao").value = t.rota_id || "";
                abrirModal("modalSituacao");
            }

            if (botao.dataset.excluir) {
                const t = trens.find((x) => x.id == botao.dataset.excluir);
                if (!confirm(`Excluir o ${t.nome}? Os sensores e leituras dele também serão apagados.`)) return;
                try {
                    await api("trens.php?id=" + t.id, "DELETE");
                    avisar(t.nome + " excluído.", "sucesso");
                    await carregar();
                } catch (erro) {
                    mostrarErro(erro);
                }
            }
        });

        document.getElementById("formTrem").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            const dados = {
                codigo: valor("codigoTrem"),
                nome: valor("nomeTrem"),
                modelo: valor("modeloTrem"),
                ano_fabricacao: valor("anoTrem"),
                capacidade_toneladas: valor("capacidadeTrem"),
                consumo: valor("consumoTrem"),
                status: statusTrem.value,
                rota_id: valor("rotaTrem")
            };
            try {
                await comCarregamento(document.getElementById("btnSalvarTrem"), () =>
                    editandoId ? api("trens.php?id=" + editandoId, "PUT", dados) : api("trens.php", "POST", dados));
                fecharModal("modalTrem");
                avisar(editandoId ? "Trem atualizado." : "Trem cadastrado.", "sucesso");
                await carregar();
            } catch (erro) {
                mostrarErro(erro);
            }
        });

        document.getElementById("formSituacao").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            try {
                await api("trens.php?id=" + situacaoId, "PATCH", { status: situacaoNova.value, rota_id: valor("rotaSituacao") });
                fecharModal("modalSituacao");
                avisar("Situação atualizada.", "sucesso");
                await carregar();
            } catch (erro) {
                if (erro.campo === "statusTrem") erro.campo = "situacaoNova";
                if (erro.campo === "rotaTrem") erro.campo = "rotaSituacao";
                mostrarErro(erro);
            }
        });
    }

    await carregar();
});
