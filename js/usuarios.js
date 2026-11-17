/* Administração de usuários (somente gestor). As regras (não desativar a si mesmo nem o último gestor,
   alteração de perfil) são verificadas no servidor em api/usuarios.php. */

iniciarPagina(async (usuario) => {
    const opcoes = await api("opcoes.php");
    const filtros = document.getElementById("filtrosUsuarios");
    const tabela = document.getElementById("tabelaUsuarios");
    const cargo = document.getElementById("cargoUsuario");
    const trem = document.getElementById("tremUsuario");
    let usuarios = [];
    let editandoId = null;

    preencherSelect(trem, opcoesTrens(opcoes.trens), "Sem trem");
    const sincronizarTrem = () => {
        trem.disabled = cargo.value !== "maquinista";
        if (trem.disabled) trem.value = "";
    };
    cargo.addEventListener("change", sincronizarTrem);

    function mostrarSenha(nome, senha) {
        document.getElementById("textoSenha").textContent = `Senha temporária de ${nome}:`;
        document.getElementById("senhaGerada").textContent = senha;
        abrirModal("modalSenha");
    }

    async function carregar() {
        usuarios = await api("usuarios.php?" + lerFiltros(filtros));
        preencherTabela(tabela, usuarios.map((u) => `
            <tr>
                <td><strong>${escapar(u.nome)}</strong>${u.id == usuario.id ? ' <small>(você)</small>' : ""}</td>
                <td>${escapar(u.email)}</td>
                <td>${escapar(NOMES_CARGO[u.cargo])}</td>
                <td>${escapar(u.funcao || "—")}</td>
                <td>${escapar(u.trem || "—")}</td>
                <td>${Number(u.ativo) ? selo("ativo") : selo("inativo", "Desativado")}</td>
                <td>${u.pedido_senha_em ? `<span class="valor-atencao">Pediu redefinição</span>` : Number(u.trocar_senha) ? "Troca pendente" : "OK"}</td>
                <td><div class="acoes-linha">
                    <button type="button" class="btn-icone" data-editar="${u.id}" title="Editar" aria-label="Editar ${escapar(u.nome)}"><i class="bi bi-pencil"></i></button>
                    <button type="button" class="btn-icone" data-senha="${u.id}" title="Redefinir senha" aria-label="Redefinir senha de ${escapar(u.nome)}"><i class="bi bi-key"></i></button>
                    ${u.id == usuario.id ? "" : Number(u.ativo)
                        ? `<button type="button" class="btn-perigo btn-pequeno" data-desativar="${u.id}">Desativar</button>`
                        : `<button type="button" class="btn-secundario btn-pequeno" data-ativar="${u.id}">Reativar</button>`}
                </div></td>
            </tr>`).join(""), "Nenhum usuário encontrado.");
    }

    filtros.addEventListener("submit", (e) => { e.preventDefault(); carregar().catch(mostrarErro); });

    document.getElementById("btnNovoUsuario").addEventListener("click", () => {
        editandoId = null;
        document.getElementById("formUsuario").reset();
        document.getElementById("ativoDesdeUsuario").value = hoje();
        document.querySelectorAll(".somente-novo").forEach((el) => (el.hidden = false));
        document.getElementById("tituloModalUsuario").textContent = "Novo usuário";
        sincronizarTrem();
        abrirModal("modalUsuario");
    });

    tabela.addEventListener("click", async (evento) => {
        const botao = evento.target.closest("button");
        if (!botao) return;
        const u = usuarios.find((x) => x.id == (botao.dataset.editar || botao.dataset.senha || botao.dataset.desativar || botao.dataset.ativar));

        try {
            if (botao.dataset.editar) {
                const d = await api("usuarios.php?id=" + u.id);
                editandoId = d.id;
                document.getElementById("tituloModalUsuario").textContent = "Editar " + d.nome;
                document.querySelectorAll(".somente-novo").forEach((el) => (el.hidden = true));
                document.getElementById("nomeUsuario").value = d.nome;
                document.getElementById("emailUsuario").value = d.email;
                cargo.value = d.cargo;
                sincronizarTrem();
                trem.value = d.trem_id || "";
                document.getElementById("funcaoUsuario").value = d.funcao || "";
                document.getElementById("telefoneUsuario").value = d.telefone || "";
                document.getElementById("cpfUsuario").value = d.cpf || "";
                document.getElementById("nascimentoUsuario").value = d.nascimento || "";
                document.getElementById("ativoDesdeUsuario").value = d.ativo_desde || "";
                document.getElementById("enderecoUsuario").value = d.endereco || "";
                abrirModal("modalUsuario");
            }
            if (botao.dataset.senha) {
                if (!confirm(`Gerar uma nova senha temporária para ${u.nome}? A senha atual deixará de funcionar.`)) return;
                const r = await api(`usuarios.php?id=${u.id}&acao=redefinir_senha`, "PATCH");
                mostrarSenha(u.nome, r.senha_temporaria);
                await carregar();
            }
            if (botao.dataset.desativar) {
                if (!confirm(`Desativar ${u.nome}? A pessoa não conseguirá mais entrar no sistema.`)) return;
                await api(`usuarios.php?id=${u.id}&acao=desativar`, "PATCH");
                avisar(u.nome + " foi desativado.", "sucesso");
                await carregar();
            }
            if (botao.dataset.ativar) {
                await api(`usuarios.php?id=${u.id}&acao=ativar`, "PATCH");
                avisar(u.nome + " foi reativado.", "sucesso");
                await carregar();
            }
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.getElementById("formUsuario").addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros(evento.target);
        const dados = {
            nome: valor("nomeUsuario"), email: valor("emailUsuario"), cargo: cargo.value, trem_id: trem.value,
            funcao: valor("funcaoUsuario"), telefone: valor("telefoneUsuario"), cpf: valor("cpfUsuario"),
            nascimento: valor("nascimentoUsuario"), ativo_desde: valor("ativoDesdeUsuario"), endereco: valor("enderecoUsuario")
        };
        if (!editandoId) dados.senha = document.getElementById("senhaUsuario").value;
        try {
            const r = await comCarregamento(document.getElementById("btnSalvarUsuario"), () =>
                editandoId ? api("usuarios.php?id=" + editandoId, "PUT", dados) : api("usuarios.php", "POST", dados));
            fecharModal("modalUsuario");
            avisar(editandoId ? "Usuário atualizado." : "Usuário cadastrado.", "sucesso");
            if (!editandoId && r.senha_temporaria) mostrarSenha(dados.nome, r.senha_temporaria);
            await carregar();
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    await carregar();
});
