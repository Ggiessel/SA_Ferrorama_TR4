/*
============================================================
  FERRORAMA TR4 — FUNÇÕES GLOBAIS
  Arquivo: js/global.js
============================================================

  Carregado em TODAS as páginas, antes do script da página.

  Os dados vêm da API em PHP (pasta api/), que conversa com o
  banco MySQL. Resumo do que tem aqui:

    TR4.api()     -> faz um pedido para a API (fetch)
    TR4.sessao    -> usuário logado e "Sair"
    TR4.iniciar() -> confere o login e roda o código da página
    TR4.avisar()  -> mensagem flutuante (toast)
    TR4.erro()    -> marca um campo do formulário com erro
    TR4.validar   -> e-mail, CPF, telefone, data
    TR4.mascara   -> formata CPF, telefone e data enquanto digita
    TR4.formatar  -> datas e dinheiro no padrão brasileiro
*/

(function () {
    "use strict";

    const TR4 = {};
    window.TR4 = TR4;

    /* ========================================================
       1. ENDEREÇO DA API
       ======================================================== */

    // A API em PHP fica na pasta api/, ao lado da pasta paginas/.
    // O site precisa ser aberto pelo Apache (XAMPP), por exemplo:
    //   http://localhost/SA_Ferrorama_TR4/paginas/login.html
    // O Live Server e o "abrir arquivo direto" não rodam PHP.
    TR4.API_URL = "../api/index.php";

    /* ========================================================
       2. PÁGINA ATUAL E PERMISSÕES
       (a API também confere; aqui é só para redirecionar rápido)
       ======================================================== */

    TR4.pagina = decodeURIComponent(location.pathname.split("/").pop() || "").replace(/\.html$/, "");

    const TODOS = ["gestor", "maquinista", "cliente"];

    // null = página pública | "logado" = só precisa estar logado
    const PERMISSOES = {
        "login": null,
        "signin": null,
        "selecao-usuario": "logado",
        "menu-gestor": ["gestor"],
        "menu-maquinista": ["maquinista"],
        "menu.cliente": ["cliente"],
        "gestao-geral": TODOS,
        "gestao-rotas": TODOS,
        "monitoramento": TODOS,
        "alertas": TODOS,
        "perfil-pessoal": TODOS,
        "manutencao": ["gestor", "maquinista"],
        "relatorios": ["gestor", "maquinista"],
        "cadastro-carga": ["gestor"],
        "gestao-sensores": ["gestor"],
        "adicionar-sensor": ["gestor"],
        "remover-sensor": ["gestor"],
        "funcionarios": ["gestor"],
        "perfil-funcionario": ["gestor"]
    };

    const MENU_DO_CARGO = {
        gestor: "menu-gestor.html",
        maquinista: "menu-maquinista.html",
        cliente: "menu.cliente.html"
    };

    TR4.menuDoCargo = function (cargo) {
        return MENU_DO_CARGO[cargo] || "selecao-usuario.html";
    };

    /* ========================================================
       3. SESSÃO
       O login fica guardado na sessão do PHP (um cookie que o
       navegador manda sozinho em todo pedido). Aqui só guardamos
       os dados do usuário enquanto a página está aberta.
       ======================================================== */

    TR4.usuario = null; // preenchido por TR4.iniciar()

    TR4.sessao = {
        entrar: function (usuario) {
            TR4.usuario = usuario;
        },
        sair: async function () {
            TR4.usuario = null;
            try {
                await TR4.api("/auth/sair", { metodo: "POST" });
            } catch (e) { /* mesmo com erro, volta para o login */ }
        }
    };

    TR4.ir = function (pagina) {
        window.location.href = pagina;
    };

    // Leva o usuário para a "página inicial" certa para ele
    TR4.irParaInicio = function (usuario) {
        usuario = usuario || TR4.usuario;
        if (!usuario) return TR4.ir("login.html");
        TR4.ir(TR4.menuDoCargo(usuario.cargo));
    };

    /* ========================================================
       4. PEDIDOS PARA A API
       ======================================================== */

    /**
     * Exemplos:
     *   const rotas = await TR4.api("/rotas");
     *   await TR4.api("/cargas", { metodo: "POST", corpo: { ... } });
     * Se a API responder com erro, lança um Error com:
     *   .message (texto para o usuário), .status e .campo (id do campo com problema)
     */
    TR4.api = async function (caminho, opcoes) {
        opcoes = opcoes || {};
        const cabecalhos = { "Accept": "application/json" };
        if (opcoes.corpo !== undefined) cabecalhos["Content-Type"] = "application/json";

        let resposta;
        try {
            resposta = await fetch(TR4.API_URL + caminho, {
                method: opcoes.metodo || "GET",
                headers: cabecalhos,
                credentials: "same-origin", // manda o cookie da sessão do PHP
                body: opcoes.corpo !== undefined ? JSON.stringify(opcoes.corpo) : undefined
            });
        } catch (e) {
            const erro = new Error("Não foi possível falar com a API. Confira se o Apache está ligado no XAMPP.");
            erro.status = 0;
            throw erro;
        }

        if (resposta.status === 204) return null;

        // Se não veio JSON, o PHP não rodou (site aberto pelo Live Server ou direto do arquivo)
        const tipo = resposta.headers.get("Content-Type") || "";
        if (tipo.indexOf("application/json") === -1) {
            const erro = new Error("A API não respondeu. Abra o site pelo Apache do XAMPP " +
                "(http://localhost/...), não pelo Live Server.");
            erro.status = 0;
            throw erro;
        }
        const dados = await resposta.json().catch(function () { return {}; });

        // Token vencido ou inválido: volta para o login
        // (no próprio login/cadastro o 401 é "senha errada", então não redireciona)
        const telaDeLogin = caminho === "/auth/login" || caminho === "/auth/cadastro";
        if (resposta.status === 401 && !telaDeLogin) {
            TR4.usuario = null;
            TR4.avisarDepois(dados.erro || "Entre na sua conta para continuar.", "aviso");
            TR4.ir("login.html");
        }

        if (!resposta.ok) {
            const erro = new Error(dados.erro || "Algo deu errado (erro " + resposta.status + ").");
            erro.status = resposta.status;
            erro.campo = dados.campo;
            throw erro;
        }
        return dados;
    };

    // Mostra o erro no campo certo (se a API disser qual é) ou num aviso
    TR4.mostrarErro = function (erro) {
        const campo = erro && erro.campo ? document.getElementById(erro.campo) : null;
        if (campo) {
            TR4.erro(campo, erro.message);
            campo.focus();
        } else {
            TR4.avisar(erro && erro.message ? erro.message : "Algo deu errado.", "erro");
        }
    };

    // Listas dos <select> (tipos de carga, cidades, sensores, rotas). Busca uma vez só por página.
    let opcoesEmCache = null;
    TR4.opcoes = function () {
        if (!opcoesEmCache) opcoesEmCache = TR4.api("/opcoes");
        return opcoesEmCache;
    };

    /* ========================================================
       5. INÍCIO DE CADA PÁGINA
       ======================================================== */

    /**
     * Cada página coloca seu código em:
     *   TR4.iniciar(async function (usuario) { ... });
     * Antes de rodar, confere o login e a permissão do cargo.
     */
    TR4.iniciar = function (funcao) {
        const rodar = async function () {
            try {
                const usuario = await verificarAcesso();
                if (usuario === false) return; // redirecionou

                prepararNavegacao(usuario);
                mostrarAvisoGuardado();
                await funcao(usuario);
            } catch (erro) {
                if (erro.status !== 401) TR4.mostrarErro(erro);
            }
        };

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", rodar);
        } else {
            rodar();
        }
    };

    // Devolve o usuário (ou null em página pública). false = foi redirecionado.
    async function verificarAcesso() {
        const regra = PERMISSOES[TR4.pagina];
        if (regra === null || regra === undefined) return null;

        // Sem login, a API responde 401 e o TR4.api() já leva para o login.html
        const usuario = await TR4.api("/auth/eu");
        TR4.usuario = usuario;

        if (regra === "logado") return usuario;
        if (!usuario.cargo) {
            TR4.ir("selecao-usuario.html");
            return false;
        }
        if (!regra.includes(usuario.cargo)) {
            TR4.avisarDepois("Seu cargo não tem acesso a essa página.", "erro");
            TR4.ir(TR4.menuDoCargo(usuario.cargo));
            return false;
        }
        return usuario;
    }

    /* ========================================================
       6. AVISOS (toast)
       ======================================================== */

    const ICONES_AVISO = {
        sucesso: "bi-check-circle",
        erro: "bi-x-octagon",
        aviso: "bi-exclamation-triangle",
        info: "bi-info-circle"
    };

    function caixaDeAvisos() {
        let caixa = document.querySelector(".tr4-avisos");
        if (!caixa) {
            caixa = document.createElement("div");
            caixa.className = "tr4-avisos";
            caixa.setAttribute("role", "status");
            caixa.setAttribute("aria-live", "polite");
            document.body.appendChild(caixa);
        }
        return caixa;
    }

    // tipo: "sucesso" | "erro" | "aviso" | "info"
    TR4.avisar = function (mensagem, tipo) {
        tipo = ICONES_AVISO[tipo] ? tipo : "info";
        const aviso = document.createElement("div");
        aviso.className = "tr4-aviso tr4-aviso--" + tipo;
        aviso.innerHTML =
            '<i class="bi ' + ICONES_AVISO[tipo] + '" aria-hidden="true"></i>' +
            "<span>" + TR4.escapar(mensagem) + "</span>" +
            '<button type="button" aria-label="Fechar aviso"><i class="bi bi-x-lg"></i></button>';

        const fechar = function () { aviso.remove(); };
        aviso.querySelector("button").addEventListener("click", fechar);
        caixaDeAvisos().appendChild(aviso);
        setTimeout(fechar, tipo === "erro" ? 7000 : 4000);
    };

    // Guarda um aviso para mostrar na PRÓXIMA página (útil antes de redirecionar)
    TR4.avisarDepois = function (mensagem, tipo) {
        try {
            sessionStorage.setItem("tr4_aviso", JSON.stringify({ mensagem: mensagem, tipo: tipo }));
        } catch (e) { /* sem sessionStorage: o aviso só não aparece */ }
    };

    function mostrarAvisoGuardado() {
        try {
            const texto = sessionStorage.getItem("tr4_aviso");
            if (!texto) return;
            sessionStorage.removeItem("tr4_aviso");
            const aviso = JSON.parse(texto);
            TR4.avisar(aviso.mensagem, aviso.tipo);
        } catch (e) { /* ignora */ }
    }

    /* ========================================================
       7. ERROS EM CAMPOS DE FORMULÁRIO
       ======================================================== */

    // O texto de erro vai depois do "embrulho" do campo, se houver
    function ancoraDoCampo(campo) {
        return campo.closest(".select-wrapper, .input-email, .inputSearch") || campo;
    }

    TR4.erro = function (campo, mensagem) {
        const ancora = ancoraDoCampo(campo);
        let texto = ancora.nextElementSibling;
        if (!texto || !texto.classList.contains("tr4-erro-texto")) {
            texto = document.createElement("p");
            texto.className = "tr4-erro-texto";
            texto.id = "erro-" + Math.random().toString(36).slice(2, 9);
            ancora.insertAdjacentElement("afterend", texto);
        }
        texto.textContent = mensagem;
        campo.setAttribute("aria-invalid", "true");
        campo.setAttribute("aria-describedby", texto.id);
    };

    TR4.limparErro = function (campo) {
        campo.removeAttribute("aria-invalid");
        campo.removeAttribute("aria-describedby");
        const texto = ancoraDoCampo(campo).nextElementSibling;
        if (texto && texto.classList.contains("tr4-erro-texto")) texto.remove();
    };

    // Valida uma lista de regras e foca o primeiro campo com erro.
    // Exemplo: TR4.conferir([[campoEmail, !TR4.validar.email(v), "E-mail inválido"]])
    // Se um campo tiver várias regras, vale a mensagem da primeira que falhar.
    TR4.conferir = function (regras) {
        regras.forEach(function (regra) { TR4.limparErro(regra[0]); });

        const marcados = [];
        regras.forEach(function (regra) {
            const campo = regra[0], temErro = regra[1], mensagem = regra[2];
            if (temErro && !marcados.includes(campo)) {
                TR4.erro(campo, mensagem);
                marcados.push(campo);
            }
        });

        if (marcados.length) marcados[0].focus();
        return marcados.length === 0;
    };

    // Ao digitar de novo, o erro some
    document.addEventListener("input", function (e) {
        if (e.target.hasAttribute && e.target.hasAttribute("aria-invalid")) TR4.limparErro(e.target);
    });
    document.addEventListener("change", function (e) {
        if (e.target.tagName === "SELECT" && e.target.hasAttribute("aria-invalid")) TR4.limparErro(e.target);
    });

    // Desliga um botão enquanto a API responde (evita clique duplo)
    TR4.ocupado = async function (botao, tarefa) {
        botao.disabled = true;
        botao.setAttribute("aria-busy", "true");
        try {
            return await tarefa();
        } finally {
            botao.disabled = false;
            botao.removeAttribute("aria-busy");
        }
    };

    /* ========================================================
       8. VALIDAÇÕES
       ======================================================== */

    TR4.soNumeros = function (texto) {
        return String(texto || "").replace(/\D/g, "");
    };

    TR4.validar = {
        email: function (texto) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(texto).trim());
        },

        telefone: function (texto) {
            const n = TR4.soNumeros(texto);
            return n.length === 10 || n.length === 11;
        },

        // Confere os dígitos verificadores do CPF
        cpf: function (texto) {
            const n = TR4.soNumeros(texto);
            if (n.length !== 11 || /^(\d)\1{10}$/.test(n)) return false;
            for (let t = 9; t < 11; t++) {
                let soma = 0;
                for (let i = 0; i < t; i++) soma += Number(n[i]) * (t + 1 - i);
                const digito = ((soma * 10) % 11) % 10;
                if (digito !== Number(n[t])) return false;
            }
            return true;
        },

        // "dd/mm/aaaa" e a data precisa existir (31/02 não vale)
        data: function (texto) {
            const partes = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(texto).trim());
            if (!partes) return false;
            const dia = Number(partes[1]), mes = Number(partes[2]), ano = Number(partes[3]);
            const d = new Date(ano, mes - 1, dia);
            return d.getFullYear() === ano && d.getMonth() === mes - 1 && d.getDate() === dia && ano > 1900;
        },

        dataNoPassado: function (texto) {
            if (!TR4.validar.data(texto)) return false;
            const p = texto.split("/");
            return new Date(p[2], p[1] - 1, p[0]) <= new Date();
        }
    };

    /* ========================================================
       9. MÁSCARAS (formatam enquanto o usuário digita)
       ======================================================== */

    TR4.mascara = {
        cpf: function (v) {
            return TR4.soNumeros(v).slice(0, 11)
                .replace(/(\d{3})(\d)/, "$1.$2")
                .replace(/(\d{3})(\d)/, "$1.$2")
                .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        },
        telefone: function (v) {
            const n = TR4.soNumeros(v).slice(0, 11);
            if (n.length <= 2) return n.length ? "(" + n : "";
            if (n.length <= 6) return "(" + n.slice(0, 2) + ") " + n.slice(2);
            if (n.length <= 10) return "(" + n.slice(0, 2) + ") " + n.slice(2, 6) + "-" + n.slice(6);
            return "(" + n.slice(0, 2) + ") " + n.slice(2, 7) + "-" + n.slice(7);
        },
        data: function (v) {
            return TR4.soNumeros(v).slice(0, 8)
                .replace(/(\d{2})(\d)/, "$1/$2")
                .replace(/(\d{2})(\d)/, "$1/$2");
        }
    };

    TR4.aplicarMascara = function (campo, tipo) {
        if (!campo) return;
        campo.addEventListener("input", function () {
            campo.value = TR4.mascara[tipo](campo.value);
        });
    };

    /* ========================================================
       10. DATAS, FORMATAÇÃO E UTILITÁRIOS
       ======================================================== */

    TR4.hojeISO = function () {
        const d = new Date();
        return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" +
            String(d.getDate()).padStart(2, "0");
    };

    // "19/10/2026" -> "2026-10-19" (formato que a API e o banco usam). Vazio -> "".
    TR4.paraISO = function (dataBr) {
        const p = String(dataBr || "").trim().split("/");
        return p.length === 3 ? p[2] + "-" + p[1] + "-" + p[0] : "";
    };

    TR4.formatar = {
        // "2026-10-19" -> "19/10/2026"
        data: function (iso) {
            if (!iso) return "";
            const p = String(iso).slice(0, 10).split("-");
            return p[2] + "/" + p[1] + "/" + p[0];
        },
        // "2026-10-19 08:10:00" -> "19/10/2026 às 08:10"
        dataHora: function (iso) {
            if (!iso) return "";
            iso = String(iso);
            return TR4.formatar.data(iso) + (iso.length > 10 ? " às " + iso.slice(11, 16) : "");
        },
        moeda: function (valor) {
            return Number(valor).toLocaleString("pt-BR", { style: "currency", currency: "BRL", maximumFractionDigits: 0 });
        },
        numero: function (valor) {
            return Number(valor).toLocaleString("pt-BR");
        }
    };

    // "em_transito" -> "em-transito" (para usar em classes CSS)
    TR4.classeStatus = function (status) {
        return String(status || "").replace(/_/g, "-");
    };

    // Para buscas: "Gestão" e "gestao" viram a mesma coisa
    TR4.normalizar = function (texto) {
        return String(texto || "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().trim();
    };

    // Evita que um texto digitado vire HTML (proteção contra XSS)
    TR4.escapar = function (texto) {
        return String(texto === undefined || texto === null ? "" : texto)
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    };

    // itens: ["A", "B"] ou [{ valor: "a", texto: "A" }]
    TR4.preencherSelect = function (select, itens, selecionado, textoVazio) {
        if (!select) return;
        select.innerHTML = "";
        if (textoVazio) {
            const vazio = new Option(textoVazio, "");
            vazio.disabled = true;
            vazio.selected = selecionado === undefined || selecionado === null;
            select.add(vazio);
        }
        itens.forEach(function (item) {
            const valor = typeof item === "object" ? item.valor : String(item);
            const texto = typeof item === "object" ? item.texto : String(item);
            select.add(new Option(texto, valor, false, String(valor) === String(selecionado)));
        });
    };

    // Debounce: espera o usuário parar de digitar antes de chamar a API
    TR4.esperarDigitar = function (funcao, ms) {
        let relogio;
        return function () {
            clearTimeout(relogio);
            relogio = setTimeout(funcao, ms || 300);
        };
    };

    /* ========================================================
       11. CABEÇALHO E BARRA LATERAL (todas as páginas)
       ======================================================== */

    function tornarClicavel(icone, rotulo, acao) {
        icone.setAttribute("role", "button");
        icone.setAttribute("tabindex", "0");
        icone.setAttribute("aria-label", rotulo);
        icone.setAttribute("title", rotulo);
        icone.addEventListener("click", acao);
        icone.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                acao();
            }
        });
    }

    function prepararNavegacao(usuario) {
        // Ícone ☰ no título -> volta para o menu do usuário
        document.querySelectorAll("h1 > i.bi-list").forEach(function (icone) {
            tornarClicavel(icone, "Voltar ao menu", function () { TR4.irParaInicio(); });
        });

        // Ícone ← no título -> volta para a página anterior
        document.querySelectorAll("h1 > i.bi-arrow-left-short").forEach(function (icone) {
            tornarClicavel(icone, "Voltar", function () {
                if (document.referrer && history.length > 1) history.back();
                else TR4.irParaInicio();
            });
        });

        // Links da barra lateral dos menus
        document.querySelectorAll('[data-link="menu"]').forEach(function (link) {
            link.addEventListener("click", function (e) {
                e.preventDefault();
                TR4.irParaInicio();
            });
        });
        document.querySelectorAll('[data-link="sair"]').forEach(function (link) {
            link.addEventListener("click", async function (e) {
                e.preventDefault();
                await TR4.sessao.sair();
                TR4.avisarDepois("Você saiu da sua conta.", "info");
                TR4.ir("login.html");
            });
        });

        // Funcionários só aparece para o gestor
        if (usuario && usuario.cargo !== "gestor") {
            document.querySelectorAll('.sidebar a[href="funcionarios.html"]').forEach(function (link) {
                link.closest("li").remove();
            });
        }

        // Botões com data-ir="pagina.html" viram links
        document.querySelectorAll("[data-ir]").forEach(function (botao) {
            botao.addEventListener("click", function () {
                TR4.ir(botao.getAttribute("data-ir"));
            });
        });
    }
})();
