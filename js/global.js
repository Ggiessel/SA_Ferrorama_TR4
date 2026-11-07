const API = "../api/";

const MENUS = {
    gestor: "menu-gestor.html",
    maquinista: "menu-maquinista.html",
    cliente: "menu.cliente.html"
};

const PERMISSOES = {
    "selecao-usuario": "logado",
    "menu-gestor": ["gestor"],
    "menu-maquinista": ["maquinista"],
    "menu.cliente": ["cliente"],
    "gestao-geral": ["gestor", "maquinista", "cliente"],
    "gestao-rotas": ["gestor", "maquinista", "cliente"],
    "monitoramento": ["gestor", "maquinista", "cliente"],
    "alertas": ["gestor", "maquinista", "cliente"],
    "perfil-pessoal": ["gestor", "maquinista", "cliente"],
    "manutencao": ["gestor", "maquinista"],
    "relatorios": ["gestor", "maquinista"],
    "cadastro-carga": ["gestor"],
    "gestao-sensores": ["gestor"],
    "adicionar-sensor": ["gestor"],
    "remover-sensor": ["gestor"],
    "funcionarios": ["gestor"],
    "perfil-funcionario": ["gestor"]
};

const NOMES_STATUS = {
    aguardando: "Aguardando",
    em_transito: "Em trânsito",
    entregue: "Entregue",
    em_rota: "Em rota",
    disponivel: "Disponível",
    manutencao: "Em manutenção",
    ativo: "Ativo",
    instalacao: "Instalação",
    remocao: "Remoção"
};

let usuarioAtual = null;

async function api(arquivo, metodo = "GET", dados = null) {
    const opcoes = { method: metodo, headers: { "Content-Type": "application/json" } };

    if (dados) {
        opcoes.body = JSON.stringify(dados);
    }

    let resposta;
    try {
        resposta = await fetch(API + arquivo, opcoes);
    } catch {
        throw new Error("Não foi possível conectar à API. Ligue o Apache no XAMPP.");
    }

    let json;
    try {
        json = await resposta.json();
    } catch {
        throw new Error("A API não respondeu. Abra o site pelo XAMPP (http://localhost/...).");
    }

    if (resposta.status === 401 && arquivo !== "login.php" && arquivo !== "cadastro.php") {
        location.href = "login.html";
    }

    if (!resposta.ok) {
        const erro = new Error(json.erro);
        erro.campo = json.campo;
        throw erro;
    }

    return json;
}

function iniciarPagina(funcao) {
    document.addEventListener("DOMContentLoaded", async () => {
        try {
            const pagina = location.pathname.split("/").pop().replace(".html", "");
            const permitidos = PERMISSOES[pagina];

            if (permitidos) {
                usuarioAtual = await api("usuario.php");

                if (permitidos !== "logado") {
                    if (!usuarioAtual.cargo) {
                        location.href = "selecao-usuario.html";
                        return;
                    }
                    if (!permitidos.includes(usuarioAtual.cargo)) {
                        irParaMenu();
                        return;
                    }
                }
            }

            configurarNavegacao();
            await funcao(usuarioAtual);
        } catch (erro) {
            mostrarErro(erro);
        }
    });
}

function irParaMenu(usuario = usuarioAtual) {
    location.href = usuario && usuario.cargo ? MENUS[usuario.cargo] : "selecao-usuario.html";
}

function configurarNavegacao() {
    document.querySelectorAll("h1 > .bi-list").forEach((icone) => {
        icone.addEventListener("click", () => irParaMenu());
    });

    document.querySelectorAll("h1 > .bi-arrow-left-short").forEach((icone) => {
        icone.addEventListener("click", () => history.back());
    });

    document.querySelectorAll("[data-link='menu']").forEach((link) => {
        link.addEventListener("click", (evento) => {
            evento.preventDefault();
            irParaMenu();
        });
    });

    document.querySelectorAll("[data-link='sair']").forEach((link) => {
        link.addEventListener("click", async (evento) => {
            evento.preventDefault();
            await api("sair.php", "POST");
            location.href = "login.html";
        });
    });

    document.querySelectorAll("[data-ir]").forEach((botao) => {
        botao.addEventListener("click", () => {
            location.href = botao.dataset.ir;
        });
    });

    if (usuarioAtual && usuarioAtual.cargo !== "gestor") {
        document.querySelectorAll(".sidebar a[href='funcionarios.html']").forEach((link) => {
            link.parentElement.remove();
        });
    }
}

function avisar(mensagem, tipo = "info") {
    let caixa = document.querySelector(".tr4-avisos");

    if (!caixa) {
        caixa = document.createElement("div");
        caixa.className = "tr4-avisos";
        document.body.appendChild(caixa);
    }

    const aviso = document.createElement("div");
    aviso.className = "tr4-aviso tr4-aviso--" + tipo;
    aviso.textContent = mensagem;
    caixa.appendChild(aviso);

    setTimeout(() => aviso.remove(), 4000);
}

function mostrarErro(erro) {
    const campo = erro.campo ? document.getElementById(erro.campo) : null;

    if (!campo) {
        avisar(erro.message, "erro");
        return;
    }

    const texto = document.createElement("p");
    texto.className = "tr4-erro-texto";
    texto.textContent = erro.message;

    const lugar = campo.closest(".select-wrapper, .input-email") || campo;
    lugar.after(texto);

    campo.setAttribute("aria-invalid", "true");
    campo.focus();
}

function limparErros() {
    document.querySelectorAll(".tr4-erro-texto").forEach((texto) => texto.remove());
    document.querySelectorAll("[aria-invalid]").forEach((campo) => campo.removeAttribute("aria-invalid"));
}

function valor(id) {
    return document.getElementById(id).value.trim();
}

function preencherSelect(select, itens, textoVazio = "") {
    select.innerHTML = "";

    if (textoVazio) {
        select.add(new Option(textoVazio, ""));
    }

    itens.forEach((item) => {
        if (typeof item === "object") {
            select.add(new Option(item.texto, item.valor));
        } else {
            select.add(new Option(item, item));
        }
    });
}

function formatarData(data) {
    if (!data) {
        return "";
    }
    const [ano, mes, dia] = data.slice(0, 10).split("-");
    return `${dia}/${mes}/${ano}`;
}

function hoje() {
    const data = new Date();
    const mes = String(data.getMonth() + 1).padStart(2, "0");
    const dia = String(data.getDate()).padStart(2, "0");
    return `${data.getFullYear()}-${mes}-${dia}`;
}

function escapar(texto) {
    const div = document.createElement("div");
    div.textContent = texto ?? "";
    return div.innerHTML;
}
