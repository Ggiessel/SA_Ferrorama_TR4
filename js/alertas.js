iniciarPagina(async (usuario) => {
    const lista = document.getElementById("listaAlertas");
    const busca = document.getElementById("search");

    async function carregar() {
        const alertas = await api("alertas.php?busca=" + encodeURIComponent(busca.value));

        if (alertas.length === 0) {
            lista.innerHTML = '<p class="estado-vazio">Nenhum alerta encontrado.</p>';
            return;
        }

        lista.innerHTML = alertas
            .map((alerta) => `
                <div class="cardAlerta cardAlerta--${alerta.tipo}">
                    <div class="texto-alerta">
                        <p>${escapar(alerta.mensagem)}</p>
                        <time>${formatarData(alerta.data)} às ${alerta.data.slice(11, 16)}</time>
                    </div>
                    ${usuario.cargo === "gestor" ? `<button type="button" class="btn-apagar" data-id="${alerta.id}"><i class="bi bi-trash3"></i></button>` : ""}
                </div>`)
            .join("");

        lista.querySelectorAll(".btn-apagar").forEach((botao) => {
            botao.addEventListener("click", async () => {
                if (!confirm("Apagar este alerta?")) {
                    return;
                }
                await api("alertas.php?id=" + botao.dataset.id, "DELETE");
                await carregar();
            });
        });
    }

    busca.addEventListener("input", carregar);
    await carregar();
});
