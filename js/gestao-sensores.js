iniciarPagina(async () => {
    const sensores = await api("sensores.php");
    const lista = document.getElementById("listaSensores");

    if (sensores.length === 0) {
        lista.innerHTML = '<li class="estado-vazio">Nenhum sensor instalado.</li>';
        return;
    }

    lista.innerHTML = sensores
        .map((sensor) => {
            let situacao = NOMES_STATUS[sensor.status];
            if (sensor.prazo) {
                situacao += " até " + formatarData(sensor.prazo);
            }

            return `
                <li class="item-lista">
                    <div>
                        <strong>${escapar(sensor.tipo)}</strong>
                        <span class="item-detalhe">${escapar(sensor.rota)}</span>
                    </div>
                    <span class="selo selo--${sensor.status}">${situacao}</span>
                </li>`;
        })
        .join("");
});
