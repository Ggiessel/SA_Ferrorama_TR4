/*
  FERRORAMA TR4 — PERFIL DO USUÁRIO (paginas/perfil-pessoal.html)

  Mostra e edita os dados de quem está logado (PUT /api/usuarios/eu).
  "Ativo desde" é preenchido no cadastro e não pode ser alterado.
*/

TR4.iniciar(function (usuario) {
    "use strict";

    const CAMPOS = ["nome", "nascimento", "ativoDesde", "email", "telefone"];
    const DATAS = ["nascimento", "ativoDesde"]; // na tela "dd/mm/aaaa", na API "aaaa-mm-dd"
    const campos = {};
    CAMPOS.forEach(function (nome) { campos[nome] = document.getElementById(nome); });

    campos.ativoDesde.readOnly = true;
    campos.email.type = "email";
    campos.nascimento.placeholder = "dd/mm/aaaa";
    campos.telefone.placeholder = "(47) 99999-9999";
    TR4.aplicarMascara(campos.nascimento, "data");
    TR4.aplicarMascara(campos.telefone, "telefone");

    function preencher() {
        CAMPOS.forEach(function (nome) {
            const valor = usuario[nome] || "";
            campos[nome].value = DATAS.includes(nome) ? TR4.formatar.data(valor) : valor;
            TR4.limparErro(campos[nome]);
        });
    }

    // Chamadas pelo onclick="" dos botões no HTML
    window.salvar = async function () {
        const v = {
            nome: campos.nome.value.trim(),
            nascimento: campos.nascimento.value.trim(),
            email: campos.email.value.trim().toLowerCase(),
            telefone: campos.telefone.value.trim()
        };

        const valido = TR4.conferir([
            [campos.nome, v.nome.length < 3, "Informe seu nome completo."],
            [campos.nascimento, v.nascimento !== "" && !TR4.validar.dataNoPassado(v.nascimento), "Use uma data válida, até hoje (dd/mm/aaaa)."],
            [campos.email, !TR4.validar.email(v.email), "Informe um e-mail válido."],
            [campos.telefone, v.telefone !== "" && !TR4.validar.telefone(v.telefone), "Telefone com DDD: (47) 99999-9999."]
        ]);
        if (!valido) return;

        v.nascimento = TR4.paraISO(v.nascimento);

        try {
            usuario = await TR4.api("/usuarios/eu", { metodo: "PUT", corpo: v });
            TR4.usuario = usuario;
            TR4.avisar("Perfil salvo.", "sucesso");
        } catch (erro) {
            TR4.mostrarErro(erro); // ex.: "Esse e-mail já é usado por outra conta"
        }
    };

    window.descartar = function () {
        preencher();
        TR4.avisar("Alterações descartadas.", "info");
    };

    preencher();
});
