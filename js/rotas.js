/* Rotas e viagens. Gestor: cadastro e edição. Maquinista: consulta e início/conclusão das viagens do seu trem.
   Cliente: consulta das rotas ativas, horários e viagens. Os filtros são aplicados no banco (APIs). */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const cliente = usuario.cargo === "cliente";
    const opcoes = await api("opcoes.php");
    const tabelaRotas = document.getElementById("tabelaRotas");
    const tabelaViagens = document.getElementById("tabelaViagens");
    const filtrosRotas = document.getElementById("filtrosRotas");
    const filtrosViagens = document.getElementById("filtrosViagens");
    let rotas = [];
    let viagens = [];
    let rotaEditando = null;
    let viagemEditando = null;

    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));
    document.querySelectorAll(".nao-cliente").forEach((el) => (el.hidden = cliente));
    if (cliente) {
        document.body.dataset.titulo = "Rotas e horários";
        document.querySelector(".app-topo h1").textContent = "Rotas e horários";
    }

    const trens = opcoes.trens ? opcoesTrens(opcoes.trens) : [];
    const rotasOpcoes = opcoes.rotas.filter((r) => Number(r.ativa) || !cliente).map((r) => ({ valor: r.id, texto: r.nome }));
    preencherSelect(document.getElementById("filtroTremRota"), trens, "Todos os trens");
    preencherSelect(document.getElementById("filtroTremViagem"), trens, "Todos os trens");
    preencherSelect(document.getElementById("filtroRotaViagem"), rotasOpcoes, "Todas as rotas");
    if (gestor) {
        preencherSelect(document.getElementById("rotaViagem"), opcoes.rotas.filter((r) => Number(r.ativa)).map((r) => ({ valor: r.id, texto: r.nome })), "Escolha a rota");
        preencherSelect(document.getElementById("tremViagem"), trens, "Escolha o trem");
        preencherSelect(document.getElementById("maquinistaViagem"), opcoes.maquinistas.map((m) => ({ valor: m.id, texto: m.nome })), "Sem maquinista definido");
    }

    // Abas
    const abas = { abaRotas: "painelRotas", abaViagens: "painelViagens" };
    function mostrarAba(id) {
        Object.entries(abas).forEach(([aba, painel]) => {
            document.getElementById(aba).setAttribute("aria-selected", String(aba === id));
            document.getElementById(painel).hidden = aba !== id;
        });
    }
    Object.keys(abas).forEach((aba) => document.getElementById(aba).addEventListener("click", () => mostrarAba(aba)));

    // ---------- Rotas ----------
    async function carregarRotas() {
        rotas = await api("rotas.php?" + lerFiltros(filtrosRotas));
        preencherTabela(tabelaRotas, rotas.map((r) => `
            <tr>
                <td><strong>${escapar(r.nome)}</strong></td>
                <td>${escapar(r.origem)} → ${escapar(r.destino)}</td>
                <td>${numero(r.distancia_km, 0)} km</td>
                <td><div class="horarios-chips">${(r.horarios || "").split(", ").filter(Boolean).map((h) => `<span>${h}</span>`).join("") || "—"}</div></td>
                <td>${escapar(r.trens || "—")}</td>
                <td>${r.viagens_em_andamento}</td>
                <td>${Number(r.ativa) ? selo("ativo", "Ativa") : selo("inativo", "Inativa")}</td>
                <td>${r.alerta ? `<span class="valor-atencao"><i class="bi bi-exclamation-triangle"></i> ${escapar(r.alerta)}</span>` : "—"}</td>
                <td><div class="acoes-linha">
                    <button type="button" class="btn-secundario btn-pequeno" data-ver-viagens="${r.id}">Viagens</button>
                    ${gestor ? `<button type="button" class="btn-icone" data-editar-rota="${r.id}" title="Editar" aria-label="Editar ${escapar(r.nome)}"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn-icone perigo" data-excluir-rota="${r.id}" title="Excluir" aria-label="Excluir ${escapar(r.nome)}"><i class="bi bi-trash3"></i></button>` : ""}
                </div></td>
            </tr>`).join(""), "Nenhuma rota encontrada.");
    }

    filtrosRotas.addEventListener("submit", (e) => { e.preventDefault(); carregarRotas().catch(mostrarErro); });

    tabelaRotas.addEventListener("click", async (evento) => {
        const botao = evento.target.closest("button");
        if (!botao) return;

        if (botao.dataset.verViagens) {
            filtrosViagens.reset();
            document.getElementById("filtroRotaViagem").value = botao.dataset.verViagens;
            await carregarViagens().catch(mostrarErro);
            mostrarAba("abaViagens");
        }
        if (botao.dataset.editarRota) {
            const r = await api("rotas.php?id=" + botao.dataset.editarRota);
            rotaEditando = r.id;
            document.getElementById("tituloModalRota").textContent = "Editar " + r.nome;
            document.getElementById("nomeRota").value = r.nome;
            document.getElementById("origemRota").value = r.origem;
            document.getElementById("destinoRota").value = r.destino;
            document.getElementById("distanciaRota").value = r.distancia_km;
            document.getElementById("passageirosRota").value = r.passageiros;
            document.getElementById("quilometragemRota").value = r.quilometragem;
            document.getElementById("horariosRota").value = r.horarios.join(", ");
            document.getElementById("alertaRota").value = r.alerta || "";
            document.getElementById("ativaRota").checked = Number(r.ativa) === 1;
            abrirModal("modalRota");
        }
        if (botao.dataset.excluirRota) {
            const r = rotas.find((x) => x.id == botao.dataset.excluirRota);
            if (!confirm(`Excluir a ${r.nome}?`)) return;
            try {
                await api("rotas.php?id=" + r.id, "DELETE");
                avisar("Rota excluída.", "sucesso");
                await carregarRotas();
            } catch (erro) {
                mostrarErro(erro);
            }
        }
    });

    // ---------- Viagens ----------
    function acoesViagem(v) {
        if (cliente) return "";
        const proprio = gestor || v.trem_id == usuario.trem_id;
        if (!proprio) return "";
        const b = [];
        if (v.status === "agendada") b.push(`<button type="button" class="btn-primario btn-pequeno" data-acao="iniciar" data-id="${v.id}">Iniciar</button>`);
        if (["em_andamento", "atrasada"].includes(v.status)) b.push(`<button type="button" class="btn-primario btn-pequeno" data-acao="concluir" data-id="${v.id}">Concluir</button>`);
        if (v.status === "em_andamento") b.push(`<button type="button" class="btn-secundario btn-pequeno" data-acao="atrasar" data-id="${v.id}">Informar atraso</button>`);
        if (gestor && ["agendada", "em_andamento", "atrasada"].includes(v.status)) b.push(`<button type="button" class="btn-perigo btn-pequeno" data-acao="cancelar" data-id="${v.id}">Cancelar</button>`);
        if (gestor) b.push(`<button type="button" class="btn-icone" data-editar-viagem="${v.id}" title="Editar" aria-label="Editar viagem"><i class="bi bi-pencil"></i></button>`);
        if (gestor && ["agendada", "cancelada"].includes(v.status)) b.push(`<button type="button" class="btn-icone perigo" data-excluir-viagem="${v.id}" title="Excluir" aria-label="Excluir viagem"><i class="bi bi-trash3"></i></button>`);
        return b.join("");
    }

    async function carregarViagens() {
        viagens = await api("viagens.php?" + lerFiltros(filtrosViagens));
        preencherTabela(tabelaViagens, viagens.map((v) => `
            <tr>
                <td>${formatarDataHora(v.partida_prevista)}</td>
                <td><strong>${escapar(v.rota)}</strong> <small>${escapar(v.origem)} → ${escapar(v.destino)}</small></td>
                <td>${escapar(v.trem)}</td>
                <td>${escapar(v.maquinista || "—")}</td>
                <td>${formatarDataHora(v.chegada_prevista)}</td>
                <td>${formatarDataHora(v.partida_real)}</td>
                <td>${formatarDataHora(v.chegada_real)}</td>
                <td>${selo(v.status)}</td>
                <td><div class="acoes-linha">${acoesViagem(v) || "—"}</div></td>
            </tr>`).join(""), "Nenhuma viagem encontrada para os filtros informados.");
    }

    filtrosViagens.addEventListener("submit", (e) => { e.preventDefault(); carregarViagens().catch(mostrarErro); });
    filtrosViagens.addEventListener("reset", () => setTimeout(() => carregarViagens().catch(mostrarErro)));

    tabelaViagens.addEventListener("click", async (evento) => {
        const botao = evento.target.closest("button");
        if (!botao) return;
        try {
            if (botao.dataset.acao) {
                const textos = { iniciar: "Iniciar esta viagem agora?", concluir: "Concluir esta viagem agora?", atrasar: "Marcar esta viagem como atrasada? Um aviso será publicado.", cancelar: "Cancelar esta viagem?" };
                if (!confirm(textos[botao.dataset.acao])) return;
                await api(`viagens.php?id=${botao.dataset.id}&acao=${botao.dataset.acao}`, "PATCH");
                avisar("Viagem atualizada.", "sucesso");
                await Promise.all([carregarViagens(), carregarRotas()]);
            }
            if (botao.dataset.editarViagem) {
                const v = viagens.find((x) => x.id == botao.dataset.editarViagem);
                viagemEditando = v.id;
                document.getElementById("tituloModalViagem").textContent = "Editar viagem";
                document.getElementById("rotaViagem").value = v.rota_id;
                document.getElementById("tremViagem").value = v.trem_id;
                document.getElementById("maquinistaViagem").value = v.maquinista_id || "";
                document.getElementById("statusViagem").value = v.status;
                document.getElementById("partidaViagem").value = v.partida_prevista.slice(0, 16).replace(" ", "T");
                document.getElementById("chegadaViagem").value = v.chegada_prevista.slice(0, 16).replace(" ", "T");
                document.getElementById("observacaoViagem").value = v.observacao || "";
                abrirModal("modalViagem");
            }
            if (botao.dataset.excluirViagem) {
                if (!confirm("Excluir esta viagem?")) return;
                await api("viagens.php?id=" + botao.dataset.excluirViagem, "DELETE");
                avisar("Viagem excluída.", "sucesso");
                await carregarViagens();
            }
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    if (gestor) {
        document.getElementById("btnNovaRota").addEventListener("click", () => {
            rotaEditando = null;
            document.getElementById("formRota").reset();
            document.getElementById("tituloModalRota").textContent = "Nova rota";
            abrirModal("modalRota");
        });

        document.getElementById("formRota").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            const dados = {
                nome: valor("nomeRota"), origem: valor("origemRota"), destino: valor("destinoRota"),
                distancia_km: valor("distanciaRota"), passageiros: valor("passageirosRota") || 0,
                quilometragem: valor("quilometragemRota") || 0, horarios: valor("horariosRota"),
                alerta: valor("alertaRota"), ativa: document.getElementById("ativaRota").checked
            };
            try {
                await comCarregamento(document.getElementById("btnSalvarRota"), () =>
                    rotaEditando ? api("rotas.php?id=" + rotaEditando, "PUT", dados) : api("rotas.php", "POST", dados));
                fecharModal("modalRota");
                avisar(rotaEditando ? "Rota atualizada." : "Rota cadastrada.", "sucesso");
                await carregarRotas();
            } catch (erro) {
                mostrarErro(erro);
            }
        });

        document.getElementById("btnNovaViagem").addEventListener("click", () => {
            viagemEditando = null;
            document.getElementById("formViagem").reset();
            document.getElementById("tituloModalViagem").textContent = "Nova viagem";
            abrirModal("modalViagem");
        });

        document.getElementById("tremViagem").addEventListener("change", (e) => {
            const m = opcoes.maquinistas.find((x) => x.trem_id == e.target.value);
            if (m && !viagemEditando) document.getElementById("maquinistaViagem").value = m.id;
        });

        document.getElementById("formViagem").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            const dados = {
                rota_id: valor("rotaViagem"), trem_id: valor("tremViagem"), maquinista_id: valor("maquinistaViagem"),
                status: valor("statusViagem"), partida_prevista: valor("partidaViagem"), chegada_prevista: valor("chegadaViagem"),
                observacao: valor("observacaoViagem")
            };
            try {
                await comCarregamento(document.getElementById("btnSalvarViagem"), () =>
                    viagemEditando ? api("viagens.php?id=" + viagemEditando, "PUT", dados) : api("viagens.php", "POST", dados));
                fecharModal("modalViagem");
                avisar(viagemEditando ? "Viagem atualizada." : "Viagem cadastrada.", "sucesso");
                await carregarViagens();
            } catch (erro) {
                mostrarErro(erro);
            }
        });
    }

    await Promise.all([carregarRotas(), carregarViagens()]);
    if (new URLSearchParams(location.search).get("aba") === "viagens") mostrarAba("abaViagens");
});
