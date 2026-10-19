/*
  FERRORAMA TR4 — LOGIN (paginas/login.html)

  Manda e-mail e senha para a API (POST /api/auth/login).
  Se der certo, a API abre a sessão e o site leva para o menu do cargo.
  Contas de teste: gestor@tr4.com, maquinista@tr4.com e
  cliente@tr4.com (senha 123456).
*/

TR4.iniciar(function () {
    "use strict";

    const form = document.getElementById("formLogin");
    const campoEmail = document.getElementById("emailLogin");
    const campoSenha = document.getElementById("senhaLogin");
    const botao = form.querySelector("button[type=submit]");

    form.addEventListener("submit", async function (evento) {
        evento.preventDefault();

        const email = campoEmail.value.trim().toLowerCase();
        const senha = campoSenha.value;

        // Confere no navegador antes de chamar a API
        const valido = TR4.conferir([
            [campoEmail, email === "", "Digite seu e-mail."],
            [campoEmail, !TR4.validar.email(email), "Digite um e-mail válido, como nome@dominio.com."],
            [campoSenha, senha === "", "Digite sua senha."]
        ]);
        if (!valido) return;

        try {
            const resposta = await TR4.ocupado(botao, function () {
                return TR4.api("/auth/login", { metodo: "POST", corpo: { email: email, senha: senha } });
            });

            TR4.sessao.entrar(resposta.usuario);
            TR4.avisarDepois("Bem-vindo, " + resposta.usuario.nome.split(" ")[0] + "!", "sucesso");
            TR4.irParaInicio(resposta.usuario);
        } catch (erro) {
            TR4.mostrarErro(erro); // ex.: "Senha incorreta" aparece embaixo do campo de senha
            if (erro.campo === "senhaLogin") campoSenha.select();
        }
    });

    // Google e Apple ainda não têm integração
    document.querySelectorAll(".btn-continuar[data-provedor]").forEach(function (botaoSocial) {
        botaoSocial.addEventListener("click", function () {
            TR4.avisar("Entrar com " + botaoSocial.dataset.provedor + " ainda não está disponível. Use e-mail e senha.", "info");
        });
    });
});
