/*
 * Testes de interface do Ferrorama TR4 (navegador real com Playwright).
 * Uso: node testes/testes-interface.js http://localhost/SA_Ferrorama_TR4
 * Requer Node.js e o pacote playwright (npm i -D playwright && npx playwright install chromium).
 * Gera testes/resultado-testes-interface.md.
 */

const { chromium, devices } = require("playwright");
const fs = require("fs");
const path = require("path");

const BASE = (process.argv[2] || "http://localhost/SA_Ferrorama_TR4").replace(/\/$/, "") + "/paginas/";
const resultados = [];

async function teste(area, nome, esperado, executar) {
    let ok = false;
    let obtido = "";
    try {
        [ok, obtido] = await executar();
    } catch (e) {
        obtido = "Exceção: " + e.message.split("\n")[0];
    }
    resultados.push([area, nome, esperado, obtido, ok ? "Aprovado" : "Reprovado"]);
    console.log(`${ok ? "[OK]     " : "[FALHOU] "} ${nome}${ok ? "" : " -> " + obtido}`);
}

async function entrar(contexto, email, senha = "123456") {
    const page = await contexto.newPage();
    await page.goto(BASE + "login.html");
    await page.fill("#emailLogin", email);
    await page.fill("#senhaLogin", senha);
    await page.click("#formLogin button[type=submit]");
    await page.waitForURL(/menu-|perfil-pessoal/, { timeout: 8000 });
    return page;
}

async function semRolagemHorizontal(page) {
    return page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);
}

(async () => {
    const browser = await chromium.launch();
    const desktop = () => browser.newContext({ viewport: { width: 1366, height: 800 }, acceptDownloads: true });

    await teste("Login", "Login pela tela leva ao início do perfil e mostra o usuário no cabeçalho", "Abre menu-gestor.html com 'Gestor Demonstração' no topo", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        const nome = await page.textContent(".app-usuario strong");
        await ctx.close();
        return [page.url().endsWith("menu-gestor.html") && nome.includes("Gestor Demonstração"), page.url().split("/").pop() + " / " + nome];
    });

    await teste("Login", "Senha errada mostra mensagem e não entra", "Aviso 'E-mail ou senha incorretos.' e continua no login", async () => {
        const ctx = await desktop();
        const page = await ctx.newPage();
        await page.goto(BASE + "login.html");
        await page.fill("#emailLogin", "gestor@tr4.com");
        await page.fill("#senhaLogin", "errada");
        await page.click("#formLogin button[type=submit]");
        const aviso = await page.waitForSelector(".tr4-aviso--erro");
        const texto = await aviso.textContent();
        const url = page.url();
        await ctx.close();
        return [texto.includes("E-mail ou senha incorretos") && url.endsWith("login.html"), texto];
    });

    await teste("Logout", "Sair encerra a sessão e o botão voltar não reabre o sistema", "Volta ao login após 'Sair' e após voltar", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "trens.html");
        await page.click(".app-sair");
        await page.waitForURL(/login\.html/);
        await page.goBack();
        await page.waitForURL(/login\.html/, { timeout: 8000 });
        const url = page.url();
        await ctx.close();
        return [url.endsWith("login.html"), url.split("/").pop()];
    });

    await teste("Permissões", "Página protegida aberta sem login redireciona ao login", "gestao-geral.html -> login.html", async () => {
        const ctx = await desktop();
        const page = await ctx.newPage();
        await page.goto(BASE + "gestao-geral.html");
        await page.waitForURL(/login\.html/, { timeout: 8000 });
        const url = page.url();
        await ctx.close();
        return [url.endsWith("login.html"), url.split("/").pop()];
    });

    await teste("Permissões", "Menu do cliente mostra só opções permitidas e URL restrita é bloqueada", "Sem Painel/Trens/Usuários; trens.html volta ao início", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "cliente@tr4.com");
        const itens = await page.$$eval(".app-nav a span", (e) => e.map((x) => x.textContent.trim()));
        await page.goto(BASE + "trens.html");
        await page.waitForURL(/menu-cliente/);
        const url = page.url();
        await ctx.close();
        const proibidos = ["Painel", "Trens", "Usuários", "Simulador", "Leituras", "Relatórios"].filter((p) => itens.includes(p));
        return [!proibidos.length && url.endsWith("menu-cliente.html"), "menu: " + itens.join(", ")];
    });

    await teste("Permissões", "Menu do maquinista não tem cadastro de usuários nem simulador", "Itens 'Meu trem', sem 'Usuários' e 'Simulador'", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "maquinista@tr4.com");
        const itens = await page.$$eval(".app-nav a span", (e) => e.map((x) => x.textContent.trim()));
        await page.goto(BASE + "trens.html");
        await page.waitForSelector("#tabelaTrens tbody tr");
        const botaoNovo = await page.isVisible("#btnNovoTrem");
        await ctx.close();
        return [itens.includes("Meu trem") && !itens.includes("Usuários") && !itens.includes("Simulador") && !botaoNovo, itens.join(", ") + (botaoNovo ? " (botão Novo trem visível!)" : "")];
    });

    await teste("Trens", "Cadastrar trem pelo formulário (modal) aparece na listagem", "Trem 91 listado após salvar", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "trens.html");
        await page.click("#btnNovoTrem");
        await page.fill("#codigoTrem", "91");
        await page.fill("#nomeTrem", "Trem 91");
        await page.fill("#modeloTrem", "GE ES44AC");
        await page.fill("#anoTrem", "2019");
        await page.fill("#capacidadeTrem", "6100");
        await page.fill("#consumoTrem", "14");
        await page.click("#btnSalvarTrem");
        await page.waitForSelector("text=Trem cadastrado.");
        const existe = await page.isVisible("#tabelaTrens >> text=Trem 91");
        await ctx.close();
        return [existe, existe ? "Trem 91 na tabela" : "não apareceu"];
    });

    await teste("Formulários", "Formulário inválido mostra erro no campo", "Mensagem abaixo do campo código", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "trens.html");
        await page.click("#btnNovoTrem");
        await page.click("#btnSalvarTrem");
        const erro = await page.waitForSelector("#modalTrem .tr4-erro-texto");
        const texto = await erro.textContent();
        const invalido = await page.getAttribute("#codigoTrem", "aria-invalid");
        await ctx.close();
        return [invalido === "true", texto];
    });

    await teste("Sensores", "Sensor sem leitura aparece com 'Sem leituras'", "Linha do Trem 10 (ADXL345) com selo 'Sem leituras'", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "sensores.html");
        await page.waitForSelector("#tabelaSensores tbody tr");
        const linha = page.locator("#tabelaSensores tbody tr", { hasText: "Trem 10" }).first();
        const texto = await linha.textContent();
        await ctx.close();
        return [texto.includes("Sem leituras"), texto.replace(/\s+/g, " ").trim().slice(0, 90)];
    });

    await teste("Alertas", "Alerta crítico aparece destacado em vermelho", "Cartão crítico com borda vermelha (rgb(179, 38, 30))", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "alertas.html");
        const cartao = await page.waitForSelector(".alerta-v2--critico");
        const cor = await cartao.evaluate((e) => getComputedStyle(e).borderLeftColor);
        await ctx.close();
        return [cor === "rgb(179, 38, 30)", cor];
    });

    await teste("Dashboard", "Painel exibe os cinco indicadores com números do banco", "5 indicadores preenchidos", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "gestao-geral.html");
        await page.waitForFunction(() => document.querySelector("#indVelocidade .indicador-valor").textContent.trim() !== "");
        const valores = await page.$$eval(".indicador", (e) => e.map((x) => x.querySelector(".indicador-rotulo").textContent.trim()));
        await ctx.close();
        return [valores.length === 5, valores.join(" | ")];
    });

    await teste("Relatórios", "Exportar CSV pela tela baixa arquivo .csv", "Download relatorio-desempenho-AAAA-MM-DD.csv", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "relatorios.html");
        await page.waitForSelector("#tabelaRelatorio tbody tr");
        const [download] = await Promise.all([page.waitForEvent("download"), page.click("#btnCsv")]);
        const nome = download.suggestedFilename();
        const conteudo = fs.readFileSync(await download.path(), "utf8");
        await ctx.close();
        return [nome.endsWith(".csv") && conteudo.includes("Trem;Viagens;") && !conteudo.includes("<"), nome];
    });

    await teste("Relatórios", "Impressão esconde menu, filtros e botões", "Sidebar e filtros invisíveis em modo de impressão", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        await page.goto(BASE + "relatorios.html");
        await page.waitForSelector("#tabelaRelatorio tbody tr");
        await page.emulateMedia({ media: "print" });
        const visiveis = await page.evaluate(() => ["#menuLateral", "#filtrosRelatorio", "#btnCsv", "#abasRelatorio"]
            .filter((s) => document.querySelector(s).getClientRects().length > 0));
        const cabecalho = await page.evaluate(() => getComputedStyle(document.querySelector("#cabecalhoImpressao")).display);
        await ctx.close();
        return [!visiveis.length && cabecalho === "block", visiveis.length ? "visíveis: " + visiveis.join(", ") : "ocultos; cabeçalho de impressão " + cabecalho];
    });

    await teste("Responsividade", "Celular: menu lateral abre pelo botão do cabeçalho", "Menu deslizante visível após o toque", async () => {
        const ctx = await browser.newContext({ ...devices["iPhone 13"] });
        const page = await entrar(ctx, "gestor@tr4.com");
        const antes = await page.isVisible(".app-nav");
        await page.click(".btn-abrir-menu");
        await page.waitForSelector("#menuLateral.show");
        const depois = await page.isVisible(".app-nav >> text=Relatórios");
        await ctx.close();
        return [!antes && depois, `antes ${antes ? "visível" : "oculto"}, depois ${depois ? "visível" : "oculto"}`];
    });

    await teste("Responsividade", "Celular: tabelas viram cartões e nenhuma tela rola na horizontal", "Sem rolagem horizontal em 10 telas; células com rótulo", async () => {
        const ctx = await browser.newContext({ ...devices["iPhone 13"] });
        const page = await entrar(ctx, "gestor@tr4.com");
        const telas = ["gestao-geral", "trens", "sensores", "leituras", "rotas", "cargas", "manutencao", "alertas", "relatorios", "usuarios"];
        const comRolagem = [];
        for (const t of telas) {
            await page.goto(BASE + t + ".html");
            await page.waitForTimeout(600);
            if (!(await semRolagemHorizontal(page))) comRolagem.push(t);
        }
        await page.goto(BASE + "trens.html");
        await page.waitForSelector("#tabelaTrens tbody tr td[data-label]");
        const display = await page.$eval("#tabelaTrens tbody tr", (e) => getComputedStyle(e).display);
        await ctx.close();
        return [!comRolagem.length && display === "block", comRolagem.length ? "rolagem em " + comRolagem.join(", ") : "linhas em modo " + display];
    });

    await teste("Responsividade", "Desktop: menu lateral fixo e visível", "Sidebar visível com 1366 px", async () => {
        const ctx = await desktop();
        const page = await entrar(ctx, "gestor@tr4.com");
        const visivel = await page.isVisible("#menuLateral");
        const botao = await page.isVisible(".btn-abrir-menu");
        await ctx.close();
        return [visivel && !botao, `sidebar ${visivel ? "visível" : "oculta"}, botão de menu ${botao ? "visível" : "oculto"}`];
    });

    await browser.close();

    const aprovados = resultados.filter((r) => r[4] === "Aprovado").length;
    console.log(`\n${aprovados} de ${resultados.length} testes aprovados.`);
    const agora = new Date();
    const data = agora.toLocaleDateString("pt-BR") + " " + agora.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
    let md = `# Resultado dos testes de interface\n\nExecutado em ${data} com \`node testes/testes-interface.js\` (Chromium). ${aprovados} de ${resultados.length} aprovados.\n\n`;
    md += "| Nº | Área | Teste | Resultado esperado | Resultado obtido | Status | Data |\n|---|---|---|---|---|---|---|\n";
    resultados.forEach((r, i) => {
        md += `| ${i + 1} | ${r.map((x) => String(x).replace(/\|/g, "/")).join(" | ")} | ${data} |\n`;
    });
    fs.writeFileSync(path.join(__dirname, "resultado-testes-interface.md"), md);
    process.exit(aprovados === resultados.length ? 0 : 1);
})();
