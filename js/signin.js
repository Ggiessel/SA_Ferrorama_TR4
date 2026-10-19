/*
  FERRORAMA TR4 — CRIAR CONTA (paginas/signin.html)

  Cadastra o usuário na API (POST /api/auth/cadastro) e
  manda para a escolha do cargo.
*/

TR4.iniciar(function () {
    "use strict";

    const form = document.getElementById("formCadastro");
    const campoNome = document.getElementById("nomeCadastro");
    const campoEmail = document.getElementById("emailCadastro");
    const campoSenha = document.getElementById("senhaCadastro");
    const botao = form.querySelector("button[type=submit]");

    form.addEventListener("submit", async function (evento) {
        evento.preventDefault();

        const nome = campoNome.value.trim().replace(/\s+/g, " ");
        const email = campoEmail.value.trim().toLowerCase();
        const senha = campoSenha.value;

        const valido = TR4.conferir([
            [campoNome, nome.length < 3, "Digite seu nome (pelo menos 3 letras)."],
            [campoEmail, !TR4.validar.email(email), "Digite um e-mail válido, como nome@dominio.com."],
            [campoSenha, senha.length < 6, "A senha precisa ter pelo menos 6 caracteres."]
        ]);
        if (!valido) return;

        try {
            const resposta = await TR4.ocupado(botao, function () {
                return TR4.api("/auth/cadastro", { metodo: "POST", corpo: { nome: nome, email: email, senha: senha } });
            });

            TR4.sessao.entrar(resposta.usuario);
            TR4.avisarDepois("Conta criada! Agora escolha o seu cargo.", "sucesso");
            TR4.ir("selecao-usuario.html");
        } catch (erro) {
            TR4.mostrarErro(erro); // ex.: "Esse e-mail já tem conta"
        }
    });
});
