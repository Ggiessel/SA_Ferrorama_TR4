# Roteiro e registro de testes — Ferrorama TR4

Os testes abaixo foram **executados** em 17/11/2026, com o banco recém-importado de `banco-de-dados/ferrorama_tr4.sql`, PHP 8.3, MariaDB 10.11 e o navegador Chromium (Playwright), em telas de 1366×800 (desktop) e iPhone 13 (celular).

Os registros completos, gerados automaticamente pelos próprios scripts, estão em:

- `testes/resultado-testes-api.md` — **58 de 58 aprovados** (`php testes/testes-api.php <endereço>`);
- `testes/resultado-testes-interface.md` — **16 de 16 aprovados** (`node testes/testes-interface.js <endereço>`).

Os scripts alteram dados; reimporte o banco antes e depois de executá-los.

## Resumo por área (seleção dos testes executados)

| Nº | Área | Teste | Resultado esperado | Resultado obtido | Status | Data |
|---|---|---|---|---|---|---|
| 1 | Login | Login com senha errada e com e-mail inexistente | 401 e a mesma mensagem "E-mail ou senha incorretos." nos dois casos | 401 e a mesma mensagem nos dois casos | Aprovado | 17/11/2026 |
| 2 | Login | Login do gestor pela tela | Abre o Início do gestor com "Gestor Demonstração" no cabeçalho | menu-gestor.html com o nome no cabeçalho | Aprovado | 17/11/2026 |
| 3 | Login / Desativação | Usuário desativado tenta entrar (paulo.alves@tr4.com) | 403 "Sua conta está desativada" | 403 com a mensagem de conta desativada | Aprovado | 17/11/2026 |
| 4 | Logout | Sair e apertar o botão voltar do navegador | Volta ao login; a API responde 401 depois de sair | login.html nos dois passos; API 401 | Aprovado | 17/11/2026 |
| 5 | Permissões | Maquinista cadastra trem por requisição direta à API | 403 | 403 | Aprovado | 17/11/2026 |
| 6 | Permissões | Maquinista abre `trens.php?id=4` e filtra leituras do trem 4 | 403 no trem; nenhuma leitura de outro trem | 403; 0 leituras de outros trens | Aprovado | 17/11/2026 |
| 7 | Permissões | Cliente abre `trens.html` e chama painel, leituras e relatórios | Volta ao Início do cliente; APIs 403 | Redirecionado; 403 nas três APIs | Aprovado | 17/11/2026 |
| 8 | Cadastro | Cadastro público enviando `cargo = gestor` | Conta criada como cliente | 201, perfil cliente | Aprovado | 17/11/2026 |
| 9 | Trens / Edição | Cadastrar o Trem 90, repetir o código 008, editar o modelo e alterar a situação | 201; 400 no código repetido; modelo alterado; situação inativo | Conforme esperado | Aprovado | 17/11/2026 |
| 10 | Sensores | Sensor novo sem leituras aparece na lista | Última leitura vazia ("Sem leituras") e 0 leituras | valor nulo, 0 leituras; selo "Sem leituras" na tela | Aprovado | 17/11/2026 |
| 11 | Leituras | Registrar 111,5 °C, 100 °C e 80 °C no sensor DS18B20 | Crítico + alerta crítico do sensor/trem; Atenção; Normal; última leitura = 80 | critico (1 alerta) / atencao / normal; última 80 na API e no banco | Aprovado | 17/11/2026 |
| 12 | Leituras / Filtros | Leitura às 23:59:30 de hoje com data final = hoje | A leitura é incluída (data final inclusiva) | 1 leitura encontrada | Aprovado | 17/11/2026 |
| 13 | Simulador | Gerar 5 leituras por sensor no Trem 08 | Linhas gravadas no banco = leituras informadas | 20 geradas, 20 a mais no banco | Aprovado | 17/11/2026 |
| 14 | Rotas | Rota com horário 25:00; cadastro e edição de rota; filtro de viagens por data, situação, trem e rota | 400 no horário; rota salva; contagem igual à consulta SQL | Conforme esperado | Aprovado | 17/11/2026 |
| 15 | Alertas | Alerta crítico na tela de alertas; maquinista gera alerta; gestor resolve | Borda vermelha; alerta gravado no trem do maquinista; situação resolvido | rgb(179, 38, 30); trem 1; resolvido | Aprovado | 17/11/2026 |
| 16 | Dashboard | Comparar os 5 indicadores com consultas SQL diretas | Valores iguais | Iguais (trens em rota, críticos, sensores críticos, viagens e velocidade média) | Aprovado | 17/11/2026 |
| 17 | Relatórios | Gerar os 4 relatórios dos últimos 30 dias e um período sem dados (jan/2000) | Linhas nos 4; período vazio sem erro e médias "—" | Linhas nos 4; 200 e médias nulas | Aprovado | 17/11/2026 |
| 18 | Exportação CSV | Exportar CSV pela tela e pela API | Arquivo .csv, `text/csv`, BOM UTF-8, `;`, vírgula decimal, acentos, sem HTML | relatorio-desempenho-AAAA-MM-DD.csv; custo "18500,00"; sem "<" | Aprovado | 17/11/2026 |
| 19 | Usuários / Desativação | Gestor tenta se desativar; outro gestor o desativa; redefinição de senha | 409; sessão do desativado cai (401); senha temporária funciona e exige troca | 409; 401; login 200 com troca obrigatória | Aprovado | 17/11/2026 |
| 20 | Responsividade | Celular: 10 telas, menu ☰, tabelas; desktop: menu fixo | Sem rolagem horizontal; menu abre; linhas viram cartões; sidebar visível no desktop | Sem rolagem; menu abriu; linhas em modo bloco; sidebar visível | Aprovado | 17/11/2026 |

## Conferências manuais feitas durante o desenvolvimento

- Larguras 1024, 1280 e 1366 px: nenhuma tabela rola na horizontal (as telas abaixo de 1200 px usam cartões).
- Impressão do relatório: menu, filtros e botões ocultos, cabeçalho de impressão visível (teste de interface nº 13).
- Script `banco-de-dados/migracao-v2.sql` aplicado sobre o banco da versão anterior: as 6 leituras antigas viraram 24 leituras por sensor, os 7 funcionários viraram usuários com troca de senha obrigatória e as APIs responderam normalmente.

## Pendente de validação no ambiente da equipe (XAMPP no Windows)

- Importar o banco pelo phpMyAdmin e abrir `http://localhost/SA_Ferrorama_TR4`.
- Confirmar que `http://localhost/SA_Ferrorama_TR4/includes/limites.php` e `.../banco-de-dados/ferrorama_tr4.sql` respondem **403** (o `.htaccess` só é aplicado pelo Apache).
- Abrir um CSV exportado no Excel em português e conferir colunas e acentos.
