/* Painel: os cinco indicadores vêm de api/painel.php, calculados no banco. */

const CORES_SITUACAO = { em_rota: "#2e9d5b", disponivel: "#06648c", manutencao: "#e0a800", inativo: "#9aa3b5" };
const CORES_NIVEL = { critico: "#b3261e", atencao: "#e0a800", normal: "#2e9d5b" };

function desenharPizza(canvas, partes) {
    const ctx = canvas.getContext("2d");
    const total = partes.reduce((soma, [quantidade]) => soma + quantidade, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (!total) {
        ctx.fillStyle = "#e2e5ec";
        ctx.beginPath();
        ctx.arc(65, 65, 60, 0, 2 * Math.PI);
        ctx.fill();
        return;
    }

    let inicio = -Math.PI / 2;
    partes.forEach(([quantidade, cor]) => {
        if (!quantidade) return;
        const fim = inicio + (quantidade / total) * 2 * Math.PI;
        ctx.beginPath();
        ctx.moveTo(65, 65);
        ctx.arc(65, 65, 60, inicio, fim);
        ctx.fillStyle = cor;
        ctx.fill();
        ctx.strokeStyle = "#fff";
        ctx.lineWidth = 2;
        ctx.stroke();
        inicio = fim;
    });

    ctx.beginPath();
    ctx.arc(65, 65, 32, 0, 2 * Math.PI);
    ctx.fillStyle = "#fff";
    ctx.fill();
    ctx.fillStyle = "#152c59";
    ctx.font = "800 22px Inter, sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(String(total), 65, 66);
}

async function carregarPainel() {
    const p = await api("painel.php");

    document.getElementById("escopoPainel").textContent =
        `Escopo: ${p.escopo === "Frota" ? "frota inteira" : p.escopo}. Atualizado em ${formatarDataHora(p.gerado_em)}.`;

    // 1. Trens por situação
    const trens = p.trens_por_situacao;
    const indTrens = document.getElementById("indTrens");
    desenharPizza(indTrens.querySelector("canvas"), Object.keys(CORES_SITUACAO).map((s) => [trens[s], CORES_SITUACAO[s]]));
    indTrens.querySelector(".lista-contagem").innerHTML = Object.keys(CORES_SITUACAO).map((s) => `
        <li><span><span class="ponto" style="background:${CORES_SITUACAO[s]}"></span>${NOMES_STATUS[s]}</span><strong>${trens[s]}</strong></li>`).join("");

    // 2. Alertas ativos por gravidade
    const alertas = p.alertas_ativos;
    const indAlertas = document.getElementById("indAlertas");
    indAlertas.classList.toggle("indicador--critico", alertas.critico > 0);
    indAlertas.querySelector(".indicador-valor").innerHTML = `${alertas.critico + alertas.atencao + alertas.normal} <small>ativos</small>`;
    indAlertas.querySelector(".lista-contagem").innerHTML = ["critico", "atencao", "normal"].map((n) => `
        <li><span><span class="ponto" style="background:${CORES_NIVEL[n]}"></span>${NOMES_NIVEL[n]}</span><strong class="${n === "critico" && alertas[n] ? "valor-critico" : ""}">${alertas[n]}</strong></li>`).join("");

    // 3. Sensores com leitura crítica
    const indSensores = document.getElementById("indSensores");
    indSensores.classList.toggle("indicador--critico", p.sensores_criticos.length > 0);
    indSensores.querySelector(".indicador-valor").innerHTML = `${p.sensores_criticos.length} <small>de ${p.sensores_monitorados} ativos</small>`;
    indSensores.querySelector(".indicador-detalhe").textContent = "Considera a leitura mais recente de cada sensor.";
    document.getElementById("listaSensoresCriticos").innerHTML = p.sensores_criticos.map((s) => `
        <li><span>${seloNivel("critico")} <strong>${escapar(s.trem)}</strong> · ${escapar(s.tipo)} (${escapar(s.grandeza)})</span>
        <span class="valor-critico">${numero(s.valor, 2, s.unidade)} <small>${formatarDataHora(s.data_hora)}</small></span></li>`).join("")
        || "<li>Nenhum sensor com leitura crítica no momento.</li>";

    // 4. Viagens em andamento
    const indViagens = document.getElementById("indViagens");
    const atrasadas = p.viagens_em_andamento.filter((v) => v.status === "atrasada").length;
    indViagens.querySelector(".indicador-valor").innerHTML = `${p.viagens_em_andamento.length} <small>viagens</small>`;
    indViagens.querySelector(".indicador-detalhe").textContent = atrasadas ? `${atrasadas} com atraso.` : "Nenhuma com atraso.";
    document.getElementById("listaViagens").innerHTML = p.viagens_em_andamento.map((v) => `
        <li><span><strong>${escapar(v.trem)}</strong> · ${escapar(v.rota)} (${escapar(v.origem)} → ${escapar(v.destino)})</span>
        <span>${selo(v.status)} <small>chegada prevista ${formatarDataHora(v.chegada_prevista)}</small></span></li>`).join("")
        || "<li>Nenhuma viagem em andamento.</li>";

    // 5. Velocidade média
    const vel = p.velocidade_media;
    const indVelocidade = document.getElementById("indVelocidade");
    indVelocidade.querySelector(".indicador-valor").innerHTML = vel.media === null ? "—" : `${numero(vel.media, 1)} <small>km/h</small>`;
    indVelocidade.querySelector(".indicador-detalhe").textContent = vel.media === null
        ? "Sem leituras de velocidade nas últimas 24 horas."
        : `${vel.leituras} leituras de ${vel.trens} trem(ns) nas últimas 24 h. Limite crítico: ${vel.limite_critico} km/h.`;

    document.getElementById("graficoVelocidade").innerHTML = vel.por_trem.map((t) => {
        const largura = Math.min(100, (Number(t.media) / 100) * 100);
        const classe = t.media > vel.limite_critico ? "valor-critico" : t.media > vel.limite_atencao ? "valor-atencao" : "";
        return `
            <div class="barra-linha">
                <span class="barra-rotulo">${escapar(t.trem)}</span>
                <span class="barra-trilho"><span class="barra-preenchida" style="width:${largura.toFixed(1)}%"></span></span>
                <span class="barra-valor ${classe}">${numero(t.media, 1)} km/h</span>
            </div>`;
    }).join("") || '<p class="apoio">Nenhuma leitura de velocidade nas últimas 24 horas.</p>';
}

iniciarPagina(async () => {
    document.getElementById("btnAtualizar").addEventListener("click", () => carregarPainel().catch(mostrarErro));
    await carregarPainel();
});
