/* Cargas: consulta para todos os perfis; cadastro e atualização do andamento pelo gestor. */

iniciarPagina(async (usuario) => {
    const gestor = usuario.cargo === "gestor";
    const filtros = document.getElementById("filtrosCargas");
    const tabela = document.getElementById("tabelaCargas");
    document.querySelectorAll(".somente-gestor").forEach((el) => (el.hidden = !gestor));

    async function carregar() {
        const cargas = await api("cargas.php?" + lerFiltros(filtros));
        preencherTabela(tabela, cargas.map((c) => `
            <tr>
                <td><strong>${escapar(c.tipo)}</strong></td>
                <td>${numero(c.toneladas, 0)} t</td>
                <td>${escapar(c.destino)}</td>
                <td>${formatarData(c.partida)}</td>
                <td>${selo(c.status)}</td>
                <td><div class="barra-progresso" role="progressbar" aria-valuenow="${c.progresso}" aria-valuemin="0" aria-valuemax="100" title="${c.progresso}%"><span style="width:${c.progresso}%"></span></div> <small>${c.progresso}%</small></td>
            </tr>`).join(""), "Nenhuma carga encontrada.");
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

    if (gestor) {
        const opcoes = await api("opcoes.php");
        preencherSelect(document.getElementById("tipoCarga"), opcoes.tiposCarga, "Escolha");
        preencherSelect(document.getElementById("tamanhoCarga"), opcoes.tamanhosCarga.map((t) => ({ valor: t, texto: t + " t" })), "Escolha");
        preencherSelect(document.getElementById("destinoCarga"), opcoes.destinos, "Escolha");

        document.getElementById("btnNovaCarga").addEventListener("click", () => {
            document.getElementById("formCarga").reset();
            document.getElementById("dataPartida").min = hoje();
            abrirModal("modalCarga");
        });

        document.getElementById("formCarga").addEventListener("submit", async (evento) => {
            evento.preventDefault();
            limparErros(evento.target);
            try {
                await comCarregamento(document.getElementById("btnSalvarCarga"), () => api("cargas.php", "POST", {
                    tipo: valor("tipoCarga"), toneladas: valor("tamanhoCarga"), destino: valor("destinoCarga"), partida: valor("dataPartida")
                }));
                fecharModal("modalCarga");
                avisar("Carga cadastrada.", "sucesso");
                await carregar();
            } catch (erro) {
                mostrarErro(erro);
            }
        });

        document.getElementById("btnAvancar").addEventListener("click", async (e) => {
            try {
                const r = await comCarregamento(e.currentTarget, () => api("cargas.php?acao=avancar", "POST"));
                avisar(r.entregues.length ? "Entregues: " + r.entregues.join(" ") : "Andamento das cargas em trânsito atualizado.", "sucesso");
                await carregar();
            } catch (erro) {
                mostrarErro(erro);
            }
        });
    }

    await carregar();
});
