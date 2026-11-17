/*
 * Ferrorama TR4 — funções comuns a todas as telas.
 * Monta o menu lateral e o cabeçalho conforme o perfil, conversa com a API e formata os dados.
 * Esconder itens do menu é só conforto: as permissões valem de fato nas APIs (pasta api/).
 */

const API = "../api/";

const MENUS = {
    gestor: "menu-gestor.html",
    maquinista: "menu-maquinista.html",
    cliente: "menu-cliente.html"
};

const NOMES_CARGO = {
    gestor: "Gestor",
    maquinista: "Maquinista",
    cliente: "Cliente"
};

/* Itens do menu lateral: [página, rótulo, ícone, perfis]. */
const ITENS_MENU = [
    ["inicio", "Início", "bi-house-door-fill", ["gestor", "maquinista", "cliente"]],
    ["gestao-geral", "Painel", "bi-speedometer2", ["gestor", "maquinista"]],
    ["trens", "Trens", "bi-train-front-fill", ["gestor"]],
    ["trens", "Meu trem", "bi-train-front-fill", ["maquinista"]],
    ["sensores", "Sensores", "bi-cpu-fill", ["gestor", "maquinista"]],
    ["leituras", "Leituras", "bi-activity", ["gestor", "maquinista"]],
    ["simulador", "Simulador", "bi-sliders", ["gestor"]],
    ["rotas", "Rotas e viagens", "bi-signpost-split-fill", ["gestor", "maquinista"]],
    ["rotas", "Rotas e horários", "bi-signpost-split-fill", ["cliente"]],
    ["cargas", "Cargas", "bi-box-seam-fill", ["gestor", "maquinista", "cliente"]],
    ["manutencao", "Manutenção", "bi-tools", ["gestor", "maquinista"]],
    ["alertas", "Alertas", "bi-bell-fill", ["gestor", "maquinista"]],
    ["alertas", "Avisos", "bi-bell-fill", ["cliente"]],
    ["relatorios", "Relatórios", "bi-file-earmark-bar-graph-fill", ["gestor", "maquinista"]],
    ["usuarios", "Usuários", "bi-people-fill", ["gestor"]],
    ["perfil-pessoal", "Meu perfil", "bi-person-circle", ["gestor", "maquinista", "cliente"]]
];

/* Perfis que podem abrir cada página (o servidor faz a mesma verificação nas APIs). */
const PERMISSOES = {
    "menu-gestor": ["gestor"],
    "menu-maquinista": ["maquinista"],
    "menu-cliente": ["cliente"],
    "gestao-geral": ["gestor", "maquinista"],
    "trens": ["gestor", "maquinista"],
    "sensores": ["gestor", "maquinista"],
    "leituras": ["gestor", "maquinista"],
    "simulador": ["gestor"],
    "rotas": ["gestor", "maquinista", "cliente"],
    "cargas": ["gestor", "maquinista", "cliente"],
    "manutencao": ["gestor", "maquinista"],
    "alertas": ["gestor", "maquinista", "cliente"],
    "relatorios": ["gestor", "maquinista"],
    "usuarios": ["gestor"],
    "perfil-pessoal": ["gestor", "maquinista", "cliente"]
};

const NOMES_STATUS = {
    aguardando: "Aguardando",
    em_transito: "Em trânsito",
    entregue: "Entregue",
    em_rota: "Em rota",
    disponivel: "Disponível",
    manutencao: "Em manutenção",
    inativo: "Inativo",
    ativo: "Ativo",
    instalacao: "Em instalação",
    remocao: "Em remoção",
    agendada: "Agendada",
    em_andamento: "Em andamento",
    concluida: "Concluída",
    atrasada: "Atrasada",
    cancelada: "Cancelada",
    aberta: "Aberta",
    resolvido: "Resolvido",
    preventiva: "Preventiva",
    corretiva: "Corretiva"
};

const NOMES_NIVEL = { normal: "Normal", atencao: "Atenção", critico: "Crítico" };

let usuarioAtual = null;

/* ------------------------------------------------------------------ */
/* API                                                                 */
/* ------------------------------------------------------------------ */

async function api(arquivo, metodo = "GET", dados = null) {
    const opcoes = {
        method: metodo,
        credentials: "same-origin",
        headers: { "Content-Type": "application/json", "X-Requested-With": "fetch" }
    };

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

    if (resposta.status === 401 && !["login.php", "cadastro.php", "senha.php"].includes(arquivo) && !paginaPublica()) {
        location.href = "login.html";
    }

    if (!resposta.ok) {
        const erro = new Error(json.erro || "Erro inesperado.");
        erro.campo = json.campo;
        erro.status = resposta.status;
        throw erro;
    }

    return json;
}

/* Baixa um arquivo (CSV) pela API, mostrando a mensagem de erro em vez de baixar um arquivo de erro. */
async function baixarArquivo(arquivo, nomePadrao) {
    const resposta = await fetch(API + arquivo, { credentials: "same-origin" });

    if (!resposta.ok) {
        let mensagem = "Não foi possível gerar o arquivo.";
        try {
            mensagem = (await resposta.json()).erro || mensagem;
        } catch {}
        throw new Error(mensagem);
    }

    const disposicao = resposta.headers.get("Content-Disposition") || "";
    const nome = (disposicao.match(/filename="?([^"]+)"?/) || [])[1] || nomePadrao;
    const link = document.createElement("a");
    link.href = URL.createObjectURL(await resposta.blob());
    link.download = nome;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}

/* ------------------------------------------------------------------ */
/* Página e layout                                                     */
/* ------------------------------------------------------------------ */

function nomePagina() {
    return location.pathname.split("/").pop().replace(".html", "") || "login";
}

function paginaPublica() {
    return !PERMISSOES[nomePagina()];
}

function iniciarPagina(funcao) {
    document.addEventListener("DOMContentLoaded", async () => {
        try {
            const permitidos = PERMISSOES[nomePagina()];

            if (permitidos) {
                usuarioAtual = await api("usuario.php");

                if (!permitidos.includes(usuarioAtual.cargo)) {
                    irParaMenu();
                    return;
                }
                if (Number(usuarioAtual.trocar_senha) && nomePagina() !== "perfil-pessoal") {
                    location.href = "perfil-pessoal.html?trocar=1";
                    return;
                }

                montarLayout();
            }

            configurarNavegacao();
            await funcao(usuarioAtual);
            document.querySelectorAll("table.table").forEach(tabelaResponsiva);
        } catch (erro) {
            mostrarErro(erro);
        }
    });
}

function irParaMenu(usuario = usuarioAtual) {
    location.href = usuario && MENUS[usuario.cargo] ? MENUS[usuario.cargo] : "login.html";
}

function montarLayout() {
    const pagina = nomePagina();
    const titulo = document.body.dataset.titulo || document.title;
    const conteudo = document.querySelector("main");
    const cargo = usuarioAtual.cargo;

    const itens = ITENS_MENU
        .filter(([, , , perfis]) => perfis.includes(cargo))
        .map(([destino, rotulo, icone]) => {
            const arquivo = destino === "inicio" ? MENUS[cargo] : destino + ".html";
            const atual = destino === pagina || (destino === "inicio" && pagina === MENUS[cargo].replace(".html", ""));
            return `
                <li>
                    <a href="${arquivo}" ${atual ? 'aria-current="page"' : ""}>
                        <i class="bi ${icone}" aria-hidden="true"></i>
                        <span>${rotulo}</span>
                    </a>
                </li>`;
        })
        .join("");

    const lateral = document.createElement("aside");
    lateral.className = "app-sidebar offcanvas-lg offcanvas-start";
    lateral.id = "menuLateral";
    lateral.tabIndex = -1;
    lateral.setAttribute("aria-label", "Menu principal");
    lateral.innerHTML = `
        <div class="app-sidebar-topo">
            <a href="${MENUS[cargo]}" class="app-logo"><img src="../img/logoTR4.png" alt="TR4 - início"></a>
            <button type="button" class="btn-fechar-menu d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#menuLateral" aria-label="Fechar menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <nav class="app-nav"><ul>${itens}</ul></nav>
        <a href="#" class="app-sair" data-link="sair"><i class="bi bi-box-arrow-right" aria-hidden="true"></i><span>Sair</span></a>`;

    const topo = document.createElement("header");
    topo.className = "app-topo";
    topo.innerHTML = `
        <button type="button" class="btn-abrir-menu d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#menuLateral" aria-controls="menuLateral" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
        </button>
        <h1>${escapar(titulo)}</h1>
        <div class="app-usuario" title="Usuário conectado">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span><span class="app-usuario-rotulo">Conectado como</span>
            <strong>${escapar(usuarioAtual.nome)}</strong>
            <small>${NOMES_CARGO[cargo]}${usuarioAtual.trem ? " · " + escapar(usuarioAtual.trem) : ""}</small></span>
        </div>`;

    const area = document.createElement("div");
    area.className = "app-area";
    area.append(topo);
    if (conteudo) {
        conteudo.classList.add("app-conteudo");
        area.append(conteudo);
    }

    document.body.classList.add("app");
    document.body.prepend(lateral, area);

    // Os modais ficam direto no <body> para aparecerem acima do fundo escurecido.
    document.querySelectorAll("main .modal").forEach((modal) => document.body.appendChild(modal));
}

window.addEventListener("pageshow", (evento) => {
    if (evento.persisted) {
        location.reload();
    }
});

function configurarNavegacao() {
    document.querySelectorAll("[data-link='sair']").forEach((link) => {
        link.addEventListener("click", async (evento) => {
            evento.preventDefault();
            try {
                await api("sair.php", "POST");
            } finally {
                location.replace("login.html");
            }
        });
    });
}

/* ------------------------------------------------------------------ */
/* Mensagens                                                           */
/* ------------------------------------------------------------------ */

function avisar(mensagem, tipo = "info") {
    let caixa = document.querySelector(".tr4-avisos");

    if (!caixa) {
        caixa = document.createElement("div");
        caixa.className = "tr4-avisos";
        caixa.setAttribute("role", "status");
        caixa.setAttribute("aria-live", "polite");
        document.body.appendChild(caixa);
    }

    const aviso = document.createElement("div");
    aviso.className = "tr4-aviso tr4-aviso--" + tipo;
    aviso.textContent = mensagem;
    caixa.appendChild(aviso);

    setTimeout(() => aviso.remove(), 5000);
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
    campo.after(texto);

    campo.setAttribute("aria-invalid", "true");
    campo.focus();
}

function limparErros(raiz = document) {
    raiz.querySelectorAll(".tr4-erro-texto").forEach((texto) => texto.remove());
    raiz.querySelectorAll("[aria-invalid]").forEach((campo) => campo.removeAttribute("aria-invalid"));
}

/* ------------------------------------------------------------------ */
/* Formulários e modais                                                */
/* ------------------------------------------------------------------ */

function valor(id) {
    const campo = document.getElementById(id);
    return campo ? campo.value.trim() : "";
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

function opcoesTrens(trens) {
    return trens.map((trem) => ({ valor: trem.id, texto: trem.nome + " (" + trem.codigo + ")" }));
}

/* Abre um modal Bootstrap e devolve a instância. */
function abrirModal(id) {
    const elemento = document.getElementById(id);
    limparErros(elemento);
    const modal = bootstrap.Modal.getOrCreateInstance(elemento);
    modal.show();
    return modal;
}

function fecharModal(id) {
    bootstrap.Modal.getOrCreateInstance(document.getElementById(id)).hide();
}

/* Lê os filtros de um formulário (campos com atributo name) para uma URLSearchParams. */
function lerFiltros(formulario) {
    const filtros = new URLSearchParams();
    formulario.querySelectorAll("[name]").forEach((campo) => {
        if (campo.type === "checkbox") {
            if (campo.checked) filtros.set(campo.name, "1");
        } else if (campo.value.trim() !== "") {
            filtros.set(campo.name, campo.value.trim());
        }
    });
    return filtros;
}

/* Botão com estado de carregamento enquanto a ação roda. */
async function comCarregamento(botao, acao) {
    const texto = botao.innerHTML;
    botao.disabled = true;
    botao.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Aguarde...';
    try {
        return await acao();
    } finally {
        botao.disabled = false;
        botao.innerHTML = texto;
    }
}

/* ------------------------------------------------------------------ */
/* Tabelas responsivas: no celular cada linha vira um cartão           */
/* ------------------------------------------------------------------ */

function tabelaResponsiva(tabela) {
    const rotulos = [...tabela.querySelectorAll("thead th")].map((th) => th.textContent.trim());
    tabela.classList.add("tabela-cartoes");
    tabela.querySelectorAll("tbody tr").forEach((linha) => {
        [...linha.children].forEach((celula, indice) => {
            if (!celula.hasAttribute("data-label") && rotulos[indice] && !celula.hasAttribute("colspan")) {
                celula.setAttribute("data-label", rotulos[indice]);
            }
        });
    });
}

/* Preenche o corpo de uma tabela e já aplica os rótulos para o modo cartão. */
function preencherTabela(tabela, linhasHtml, mensagemVazia) {
    const colunas = tabela.querySelectorAll("thead th").length || 1;
    tabela.querySelector("tbody").innerHTML =
        linhasHtml || `<tr><td colspan="${colunas}" class="estado-vazio">${escapar(mensagemVazia)}</td></tr>`;
    tabelaResponsiva(tabela);
}

/* ------------------------------------------------------------------ */
/* Formatação (padrão brasileiro)                                      */
/* ------------------------------------------------------------------ */

function formatarData(data) {
    if (!data) {
        return "—";
    }
    const [ano, mes, dia] = String(data).slice(0, 10).split("-");
    return `${dia}/${mes}/${ano}`;
}

function formatarDataHora(dataHora) {
    if (!dataHora) {
        return "—";
    }
    return formatarData(dataHora) + " " + String(dataHora).slice(11, 16);
}

function numero(valorNumero, casas = 2, unidade = "") {
    if (valorNumero === null || valorNumero === undefined || valorNumero === "" || Number.isNaN(Number(valorNumero))) {
        return "—";
    }
    const texto = Number(valorNumero).toLocaleString("pt-BR", { minimumFractionDigits: casas, maximumFractionDigits: casas });
    return unidade ? texto + " " + unidade : texto;
}

function moeda(valorNumero) {
    if (valorNumero === null || valorNumero === undefined || valorNumero === "") {
        return "—";
    }
    return Number(valorNumero).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function formatarValor(valorCampo, tipo) {
    switch (tipo) {
        case "inteiro": return valorCampo === null ? "—" : numero(valorCampo, 0);
        case "numero": return numero(valorCampo, valorCampo !== null && Math.abs(valorCampo) >= 100 ? 1 : 2);
        case "moeda": return moeda(valorCampo);
        case "porcentagem": return valorCampo === null ? "—" : numero(valorCampo, 1) + "%";
        case "data": return formatarData(valorCampo);
        case "datahora": return formatarDataHora(valorCampo);
        default: return escapar(valorCampo ?? "—");
    }
}

function selo(status, texto = NOMES_STATUS[status] || status) {
    return `<span class="selo selo--${escapar(status)}">${escapar(texto)}</span>`;
}

function seloNivel(nivel) {
    if (!nivel) {
        return '<span class="selo selo--sem-dados">Sem leituras</span>';
    }
    const icones = { normal: "bi-check-circle-fill", atencao: "bi-exclamation-triangle-fill", critico: "bi-x-octagon-fill" };
    return `<span class="selo selo-nivel selo-nivel--${nivel}"><i class="bi ${icones[nivel]}" aria-hidden="true"></i> ${NOMES_NIVEL[nivel]}</span>`;
}

function hoje() {
    const data = new Date();
    const mes = String(data.getMonth() + 1).padStart(2, "0");
    const dia = String(data.getDate()).padStart(2, "0");
    return `${data.getFullYear()}-${mes}-${dia}`;
}

function diasAtras(dias) {
    const data = new Date();
    data.setDate(data.getDate() - dias);
    const mes = String(data.getMonth() + 1).padStart(2, "0");
    const dia = String(data.getDate()).padStart(2, "0");
    return `${data.getFullYear()}-${mes}-${dia}`;
}

function escapar(texto) {
    const div = document.createElement("div");
    div.textContent = texto ?? "";
    return div.innerHTML.replace(/"/g, "&quot;");
}
