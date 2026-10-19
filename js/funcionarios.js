/*
  FERRORAMA TR4 — FUNCIONÁRIOS
  Usado em duas páginas:
    - paginas/funcionarios.html       -> GET /api/funcionarios?busca=...
    - paginas/perfil-funcionario.html -> GET e PUT /api/funcionarios/:id
                                         (abre com ?id=1 no endereço)
*/

TR4.iniciar(async function () {
    "use strict";

    if (TR4.pagina === "funcionarios") await iniciarLista();
    if (TR4.pagina === "perfil-funcionario") await iniciarPerfil();

    /* ========================================================
       LISTA
       ======================================================== */
    async function iniciarLista() {
        const lista = document.getElementById("listaFuncionarios");
        const campoBusca = document.getElementById("buscaFuncionario");
        const botaoPesquisar = document.getElementById("btnPesquisar");

        async function carregar() {
            const termo = campoBusca.value.trim();
            const funcionarios = await TR4.api("/funcionarios" + (termo ? "?busca=" + encodeURIComponent(termo) : ""));

            if (funcionarios.length === 0) {
                lista.innerHTML = '<p class="estado-vazio">Nenhum funcionário encontrado para "' +
                    TR4.escapar(termo) + '".</p>';
                return;
            }

            lista.innerHTML = funcionarios.map(function (f) {
                return (
                    '<a class="funcionarios" href="perfil-funcionario.html?id=' + encodeURIComponent(f.id) + '">' +
                    "<span>" + TR4.escapar(f.nome) + "</span>" +
                    '<span class="item-detalhe">' + TR4.escapar(f.cargo) + "</span>" +
                    "</a>"
                );
            }).join("");
        }

        campoBusca.addEventListener("input", TR4.esperarDigitar(function () {
            carregar().catch(TR4.mostrarErro);
        }));
        botaoPesquisar.addEventListener("click", function () {
            carregar().catch(TR4.mostrarErro);
            campoBusca.focus();
        });

        lista.innerHTML = '<p class="estado-vazio">Carregando funcionários…</p>';
        await carregar();
    }

    /* ========================================================
       PERFIL DO FUNCIONÁRIO
       ======================================================== */
    async function iniciarPerfil() {
        const id = new URLSearchParams(location.search).get("id");

        const CAMPOS = ["cargo", "nome", "ativoDesde", "email", "telefone", "cpf", "nascimento", "endereco"];
        const DATAS = ["ativoDesde", "nascimento"]; // na tela "dd/mm/aaaa", na API "aaaa-mm-dd"
        const campos = {};
        CAMPOS.forEach(function (nome) { campos[nome] = document.getElementById(nome); });

        TR4.aplicarMascara(campos.telefone, "telefone");
        TR4.aplicarMascara(campos.cpf, "cpf");
        TR4.aplicarMascara(campos.ativoDesde, "data");
        TR4.aplicarMascara(campos.nascimento, "data");
        campos.email.type = "email";

        let funcionario;
        try {
            funcionario = await TR4.api("/funcionarios/" + encodeURIComponent(id || "0"));
        } catch (erro) {
            TR4.avisarDepois(erro.message, "erro");
            TR4.ir("funcionarios.html");
            return;
        }

        function preencher() {
            CAMPOS.forEach(function (nome) {
                const valor = funcionario[nome] || "";
                campos[nome].value = DATAS.includes(nome) ? TR4.formatar.data(valor) : valor;
                TR4.limparErro(campos[nome]);
            });
            document.title = funcionario.nome + " — Perfil do Funcionário";
        }

        // As funções abaixo são chamadas pelo onclick="" dos botões no HTML
        window.salvar = async function () {
            const v = {};
            CAMPOS.forEach(function (nome) { v[nome] = campos[nome].value.trim(); });

            const valido = TR4.conferir([
                [campos.cargo, v.cargo === "", "Informe o cargo."],
                [campos.nome, v.nome.length < 3, "Informe o nome completo."],
                [campos.ativoDesde, !TR4.validar.dataNoPassado(v.ativoDesde), "Use uma data válida, até hoje (dd/mm/aaaa)."],
                [campos.email, !TR4.validar.email(v.email), "Informe um e-mail válido."],
                [campos.telefone, v.telefone !== "" && !TR4.validar.telefone(v.telefone), "Telefone com DDD: (47) 99999-9999."],
                [campos.cpf, v.cpf !== "" && !TR4.validar.cpf(v.cpf), "CPF inválido. Confira os números."],
                [campos.nascimento, v.nascimento !== "" && !TR4.validar.dataNoPassado(v.nascimento), "Use uma data válida, até hoje (dd/mm/aaaa)."]
            ]);
            if (!valido) {
                TR4.avisar("Corrija os campos marcados para salvar.", "erro");
                return;
            }

            DATAS.forEach(function (nome) { v[nome] = TR4.paraISO(v[nome]); });

            try {
                funcionario = await TR4.api("/funcionarios/" + funcionario.id, { metodo: "PUT", corpo: v });
                TR4.avisarDepois("Dados de " + funcionario.nome + " salvos.", "sucesso");
                TR4.ir("funcionarios.html");
            } catch (erro) {
                TR4.mostrarErro(erro); // ex.: "Esse CPF já é de outro funcionário"
            }
        };

        window.descartar = function () {
            preencher();
            TR4.avisar("Alterações descartadas.", "info");
        };

        preencher();
    }
});
