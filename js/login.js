/* Login: se já houver sessão, vai direto ao início do perfil. Inclui o pedido de recuperação de senha. */

iniciarPagina(async () => {
    const sessao = await fetch(API + "usuario.php", { credentials: "same-origin" }).catch(() => null);

    if (sessao && sessao.ok) {
        irParaMenu(await sessao.json());
        return;
    }

    const formLogin = document.getElementById("formLogin");
    const formRecuperar = document.getElementById("formRecuperar");

    formLogin.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();

        try {
            const usuario = await api("login.php", "POST", {
                email: valor("emailLogin"),
                senha: document.getElementById("senhaLogin").value
            });
            if (Number(usuario.trocar_senha)) {
                location.href = "perfil-pessoal.html?trocar=1";
                return;
            }
            irParaMenu(usuario);
        } catch (erro) {
            mostrarErro(erro);
        }
    });

    document.getElementById("linkEsqueci").addEventListener("click", (evento) => {
        evento.preventDefault();
        formRecuperar.hidden = false;
        document.getElementById("emailRecuperar").value = valor("emailLogin");
        document.getElementById("emailRecuperar").focus();
    });

    document.getElementById("linkVoltarLogin").addEventListener("click", (evento) => {
        evento.preventDefault();
        formRecuperar.hidden = true;
        document.getElementById("emailLogin").focus();
    });

    formRecuperar.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        limparErros();
        try {
            const r = await api("senha.php", "POST", { email: valor("emailRecuperar") });
            avisar(r.mensagem, "sucesso");
            formRecuperar.hidden = true;
        } catch (erro) {
            mostrarErro(erro);
        }
    });
});
