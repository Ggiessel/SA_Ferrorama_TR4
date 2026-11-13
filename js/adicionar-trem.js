iniciarPagina(async () => {
    const opcoes = await api("opcoes.php");
    const formulario = document.getElementById("formTrem");
    const status = document.getElementById("statusTrem");
    const rota = document.getElementById("rotaTrem");
    const botaoSalvar = document.getElementById("btnSalvarTrem");
    const botaoCancelar = document.getElementById("btnCancelarTrem");
    let trens = [];
    let editandoId = null;

    preencherSelect(rota, opcoes.rotas.map((r) => ({ valor: r.id, texto: r.nome })), "Sem rota");

    function atualizarRota() {
        rota.disabled = status.value !== "em_rota";
        if (rota.disabled) {
            rota.value = "";
        }
    }

    function limparFormulario() {
        formulario.reset();
        editandoId = null;
        botaoSalvar.textContent = "Cadastrar";
        botaoCancelar.hidden = true;
        atualizarRota();
    }

    async function carregarTrens() {
        trens = await api("trens.php");

        document.querySelector("#tabelaTrens tbody").innerHTML = trens
            .map((trem) => `
                <tr>
                    <td>${trem.codigo}</td>
                    <td>${escapar(trem.nome)}</td>
                    <td>${escapar(trem.modelo)}</td>
                    <td>${trem.ano_fabricacao}</td>
                    <td>${Number(trem.capacidade_toneladas).toLocaleString("pt-BR")} t</td>
                    <td><span class="selo selo--${trem.status}">${NOMES_STATUS[trem.status]}</span></td>
                    <td>${escapar(trem.rota || "-")}</td>
                    <td class="acoes-tabela">
                        <button type="button" class="btn-secundario btn-pequeno" data-editar="${trem.id}">Editar</button>
                        <button type="button" class="btn-perigo btn-pequeno" data-excluir="${trem.id}">Excluir</button>
                    </td>
                </tr>`)
            .join("") || '<tr><td colspan="8">Nenhum trem cadastrado.</td></tr>';
    }

    status.addEventListener("change", atualizarRota);
    botaoCancelar.addEventListener("click", limparFormulario);

    formulario.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        const dados = {
            codigo: valor("codigoTrem"),
            nome: valor("nomeTrem"),
            modelo: valor("modeloTrem"),
            ano_fabricacao: valor("anoTrem"),
            capacidade_toneladas: valor("capacidadeTrem"),
            consumo: valor("consumoTrem"),
            status: status.value,
            rota_id: rota.value
        };

        try {
            if (editandoId) {
                await api("trens.php?id=" + editandoId, "PUT", dados);
                avisar("Trem atualizado.", "sucesso");
            } else {
                await api("trens.php", "POST", dados);
                avisar("Trem cadastrado.", "sucesso");
            }
            limparFormulario();
            await carregarTrens();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.getElementById("tabelaTrens").addEventListener("click", async (evento) => {
        const editar = evento.target.closest("[data-editar]");
        const excluir = evento.target.closest("[data-excluir]");

        if (editar) {
            const trem = trens.find((t) => t.id == editar.dataset.editar);
            limparErros();
            document.getElementById("codigoTrem").value = trem.codigo;
            document.getElementById("nomeTrem").value = trem.nome;
            document.getElementById("modeloTrem").value = trem.modelo;
            document.getElementById("anoTrem").value = trem.ano_fabricacao;
            document.getElementById("capacidadeTrem").value = Number(trem.capacidade_toneladas);
            document.getElementById("consumoTrem").value = Number(trem.consumo);
            status.value = trem.status;
            atualizarRota();
            rota.value = trem.rota_id || "";
            editandoId = trem.id;
            botaoSalvar.textContent = "Salvar alterações";
            botaoCancelar.hidden = false;
            window.scrollTo({ top: 0, behavior: "smooth" });
        }

        if (excluir) {
            const trem = trens.find((t) => t.id == excluir.dataset.excluir);

            if (!confirm("Excluir o " + trem.nome + "? As leituras e os sensores dele também serão apagados.")) {
                return;
            }

            try {
                await api("trens.php?id=" + trem.id, "DELETE");
                avisar("Trem excluído.", "sucesso");
                if (editandoId == trem.id) {
                    limparFormulario();
                }
                await carregarTrens();
            } catch (erro) {
                mostrarErro(erro);
            }
        }
    });

    atualizarRota();
    await carregarTrens();
});
