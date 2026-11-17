/* Sensores: lista com a última leitura real de cada sensor (sensores sem leitura aparecem como "Sem leituras").
   Gestor cadastra, edita e exclui/desativa. Maquinista consulta os sensores do próprio trem. */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const tabela = document.getElementById("tabelaSensores");
    const filtros = document.getElementById("filtrosSensores");
    const opcoes = await api("opcoes.php");
    const tipo = document.getElementById("tipoSensor");
    let sensores = [];
    let editandoId = null;

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));

    preencherSelect(document.getElementById("filtroTremSensor"), opcoesTrens(opcoes.trens), "Todos os trens");
    preencherSelect(document.getElementById("tremSensor"), opcoesTrens(opcoes.trens), "Escolha o trem");
    preencherSelect(tipo, opcoes.tiposSensor.map((t) => ({ valor: t.tipo, texto: `${t.tipo} — ${t.grandeza}` })), "Escolha o modelo");

    const parametro = new URLSearchParams(location.search).get("trem");
    if (parametro && gestor) document.getElementById("filtroTremSensor").value = parametro;

    tipo.addEventListener("change", () => {
        const t = opcoes.tiposSensor.find((x) => x.tipo === tipo.value);
        const l = t ? opcoes.limites[t.chave] : null;
        document.getElementById("limiteSensor").textContent = l
            ? `Limites de ${l.nome.toLowerCase()}: atenção ${l.direcao === "abaixo" ? "abaixo de" : "acima de"} ${numero(l.atencao, 1)} ${l.unidade}; crítico ${l.direcao === "abaixo" ? "abaixo de" : "acima de"} ${numero(l.critico, 1)} ${l.unidade}.`
            : "";
    });

    function classeValor(nivel) {
        return nivel === "critico" ? "valor-critico" : nivel === "atencao" ? "valor-atencao" : "";
    }

    async function carregar() {
        sensores = await api("sensores.php?" + lerFiltros(filtros));
        preencherTabela(tabela, sensores.map((s) => {
            let situacao = selo(s.status);
            if (s.prazo) situacao += ` <small>até ${formatarData(s.prazo)}</small>`;
            return `
            <tr>
                <td><strong>${escapar(s.trem)}</strong></td>
                <td>${escapar(s.tipo)}</td>
                <td>${escapar(s.grandeza)}</td>
                <td>${situacao}</td>
                <td class="${classeValor(s.ultimo_nivel)}">${s.ultimo_valor === null ? "—" : numero(s.ultimo_valor, 2, s.unidade)}</td>
                <td>${seloNivel(s.ultimo_nivel)}</td>
                <td>${formatarDataHora(s.ultima_data)}</td>
                <td>${s.total_leituras}</td>
                <td><div class="acoes-linha">
                    <a class="btn-secundario btn-pequeno" href="leituras.html?sensor=${s.id}&trem=${s.trem_id}">Histórico</a>
                    ${gestor ? `
                    <button type="button" class="btn-icone" data-editar="${s.id}" title="Editar" aria-label="Editar sensor ${escapar(s.tipo)}"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn-icone perigo" data-excluir="${s.id}" title="Excluir ou desativar" aria-label="Excluir sensor ${escapar(s.tipo)}"><i class="bi bi-trash3"></i></button>` : ""}
                </div></td>
            </tr>`;
        }).join(""), "Nenhum sensor encontrado.");
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

    if (gestor) {
        document.getElementById("btnNovoSensor").addEventListener("click", () => {
            editandoId = null;
            document.getElementById("formSensor").reset();
            document.getElementById("limiteSensor").textContent = "";
            document.getElementById("tituloModalSensor").textContent = "Novo sensor";
            abrirModal("modalSensor");
        });

        tabela.addEventListener("click", async (evento) => {
            const botao = evento.target.closest("button");
            if (!botao) return;
            const s = sensores.find((x) => x.id == (botao.dataset.editar || botao.dataset.excluir));

            if (botao.dataset.editar) {
                editandoId = s.id;
                document.getElementById("tituloModalSensor").textContent = `Editar sensor ${s.tipo} (${s.trem})`;
                tipo.value = s.tipo;
                tipo.dispatchEvent(new Event("change"));
                document.getElementById("tremSensor").value = s.trem_id;
                document.getElementById("statusSensor").value = s.status;
                document.getElementById("prazoSensor").value = s.prazo || "";
                document.getElementById("observacaoSensor").value = s.observacao || "";
                abrirModal("modalSensor");
            }

            if (botao.dataset.excluir) {
                if (!confirm(`Excluir o sensor ${s.tipo} do ${s.trem}? Se ele já tiver leituras, será apenas desativado para manter o histórico.`)) return;
                try {
                    const r = await api("sensores.php?id=" + s.id, "DELETE");
                    avisar(r.desativado ? r.mensagem : "Sensor excluído.", "sucesso");
                    await carregar();
                } catch (erro) {
                    mostrarErro(erro);
                }
            }
        });

        document.getElementById("formSensor").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            const dados = {
                tipo: tipo.value,
                trem_id: valor("tremSensor"),
                status: valor("statusSensor"),
                prazo: valor("prazoSensor"),
                observacao: valor("observacaoSensor")
            };
            try {
                await comCarregamento(document.getElementById("btnSalvarSensor"), () =>
                    editandoId ? api("sensores.php?id=" + editandoId, "PUT", dados) : api("sensores.php", "POST", dados));
                fecharModal("modalSensor");
                avisar(editandoId ? "Sensor atualizado." : "Sensor cadastrado.", "sucesso");
                await carregar();
            } catch (erro) {
                mostrarErro(erro);
            }
        });
    }

    await carregar();
});
