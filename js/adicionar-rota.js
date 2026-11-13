iniciarPagina(async () => {
    const formulario = document.getElementById("formRota");
    const botaoSalvar = document.getElementById("btnSalvarRota");
    const botaoCancelar = document.getElementById("btnCancelarRota");
    let rotas = [];
    let editandoId = null;

    function limparFormulario() {
        formulario.reset();
        editandoId = null;
        botaoSalvar.textContent = "Cadastrar";
        botaoCancelar.hidden = true;
    }

    async function carregarRotas() {
        rotas = await api("rotas.php");

        document.querySelector("#tabelaRotas tbody").innerHTML = rotas
            .map((rota) => `
                <tr>
                    <td>${escapar(rota.nome)}</td>
                    <td>${escapar(rota.origem)}</td>
                    <td>${escapar(rota.destino)}</td>
                    <td>${Number(rota.quilometragem).toLocaleString("pt-BR")} km</td>
                    <td>${Number(rota.passageiros).toLocaleString("pt-BR")}</td>
                    <td class="acoes-tabela">
                        <button type="button" class="btn-secundario btn-pequeno" data-editar="${rota.id}">Editar</button>
                        <button type="button" class="btn-perigo btn-pequeno" data-excluir="${rota.id}">Excluir</button>
                    </td>
                </tr>`)
            .join("") || '<tr><td colspan="6">Nenhuma rota cadastrada.</td></tr>';
    }

    botaoCancelar.addEventListener("click", limparFormulario);

    formulario.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        const dados = {
            nome: valor("nomeRota"),
            origem: valor("origemRota"),
            destino: valor("destinoRota"),
            quilometragem: valor("quilometragemRota"),
            passageiros: valor("passageirosRota"),
            horarios: valor("horariosRota"),
            alerta: valor("alertaRota")
        };

        try {
            if (editandoId) {
                await api("rotas.php?id=" + editandoId, "PUT", dados);
                avisar("Rota atualizada.", "sucesso");
            } else {
                await api("rotas.php", "POST", dados);
                avisar("Rota cadastrada! Ela já aparece em Gestão de Rotas.", "sucesso");
            }
            limparFormulario();
            await carregarRotas();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.getElementById("tabelaRotas").addEventListener("click", async (evento) => {
        const editar = evento.target.closest("[data-editar]");
        const excluir = evento.target.closest("[data-excluir]");

        if (editar) {
            try {
                const rota = await api("rotas.php?id=" + editar.dataset.editar);
                limparErros();
                document.getElementById("nomeRota").value = rota.nome;
                document.getElementById("origemRota").value = rota.origem;
                document.getElementById("destinoRota").value = rota.destino;
                document.getElementById("quilometragemRota").value = rota.quilometragem;
                document.getElementById("passageirosRota").value = rota.passageiros;
                document.getElementById("horariosRota").value = rota.horarios.join(", ");
                document.getElementById("alertaRota").value = rota.alerta || "";
                editandoId = rota.id;
                botaoSalvar.textContent = "Salvar alterações";
                botaoCancelar.hidden = false;
                window.scrollTo({ top: 0, behavior: "smooth" });
            } catch (erro) {
                mostrarErro(erro);
            }
        }

        if (excluir) {
            const rota = rotas.find((r) => r.id == excluir.dataset.excluir);

            if (!confirm("Excluir a " + rota.nome + "? Os horários dela também serão apagados.")) {
                return;
            }

            try {
                await api("rotas.php?id=" + rota.id, "DELETE");
                avisar("Rota excluída.", "sucesso");
                if (editandoId == rota.id) {
                    limparFormulario();
                }
                await carregarRotas();
            } catch (erro) {
                mostrarErro(erro);
            }
        }
    });

    await carregarRotas();
});
