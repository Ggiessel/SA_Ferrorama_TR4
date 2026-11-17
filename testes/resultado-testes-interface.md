# Resultado dos testes de interface

Executado em 17/11/2026 12:48 com `node testes/testes-interface.js` (Chromium). 16 de 16 aprovados.

| Nº | Área | Teste | Resultado esperado | Resultado obtido | Status | Data |
|---|---|---|---|---|---|---|
| 1 | Login | Login pela tela leva ao início do perfil e mostra o usuário no cabeçalho | Abre menu-gestor.html com 'Gestor Demonstração' no topo | menu-gestor.html / Gestor Demonstração | Aprovado | 17/11/2026 12:48 |
| 2 | Login | Senha errada mostra mensagem e não entra | Aviso 'E-mail ou senha incorretos.' e continua no login | E-mail ou senha incorretos. | Aprovado | 17/11/2026 12:48 |
| 3 | Logout | Sair encerra a sessão e o botão voltar não reabre o sistema | Volta ao login após 'Sair' e após voltar | login.html | Aprovado | 17/11/2026 12:48 |
| 4 | Permissões | Página protegida aberta sem login redireciona ao login | gestao-geral.html -> login.html | login.html | Aprovado | 17/11/2026 12:48 |
| 5 | Permissões | Menu do cliente mostra só opções permitidas e URL restrita é bloqueada | Sem Painel/Trens/Usuários; trens.html volta ao início | menu: Início, Rotas e horários, Cargas, Avisos, Meu perfil | Aprovado | 17/11/2026 12:48 |
| 6 | Permissões | Menu do maquinista não tem cadastro de usuários nem simulador | Itens 'Meu trem', sem 'Usuários' e 'Simulador' | Início, Painel, Meu trem, Sensores, Leituras, Rotas e viagens, Cargas, Manutenção, Alertas, Relatórios, Meu perfil | Aprovado | 17/11/2026 12:48 |
| 7 | Trens | Cadastrar trem pelo formulário (modal) aparece na listagem | Trem 91 listado após salvar | Trem 91 na tabela | Aprovado | 17/11/2026 12:48 |
| 8 | Formulários | Formulário inválido mostra erro no campo | Mensagem abaixo do campo código | Informe um código de até 3 números (ex.: 015). | Aprovado | 17/11/2026 12:48 |
| 9 | Sensores | Sensor sem leitura aparece com 'Sem leituras' | Linha do Trem 10 (ADXL345) com selo 'Sem leituras' | Trem 10 ADXL345 Vibração Ativo — Sem leituras — 0 Histórico | Aprovado | 17/11/2026 12:48 |
| 10 | Alertas | Alerta crítico aparece destacado em vermelho | Cartão crítico com borda vermelha (rgb(179, 38, 30)) | rgb(179, 38, 30) | Aprovado | 17/11/2026 12:48 |
| 11 | Dashboard | Painel exibe os cinco indicadores com números do banco | 5 indicadores preenchidos | Trens por situação / Alertas ativos por gravidade / Sensores com leitura crítica / Viagens em andamento / Velocidade média da frota | Aprovado | 17/11/2026 12:48 |
| 12 | Relatórios | Exportar CSV pela tela baixa arquivo .csv | Download relatorio-desempenho-AAAA-MM-DD.csv | relatorio-desempenho-2026-11-17.csv | Aprovado | 17/11/2026 12:48 |
| 13 | Relatórios | Impressão esconde menu, filtros e botões | Sidebar e filtros invisíveis em modo de impressão | ocultos; cabeçalho de impressão block | Aprovado | 17/11/2026 12:48 |
| 14 | Responsividade | Celular: menu lateral abre pelo botão do cabeçalho | Menu deslizante visível após o toque | antes oculto, depois visível | Aprovado | 17/11/2026 12:48 |
| 15 | Responsividade | Celular: tabelas viram cartões e nenhuma tela rola na horizontal | Sem rolagem horizontal em 10 telas; células com rótulo | linhas em modo block | Aprovado | 17/11/2026 12:48 |
| 16 | Responsividade | Desktop: menu lateral fixo e visível | Sidebar visível com 1366 px | sidebar visível, botão de menu oculto | Aprovado | 17/11/2026 12:48 |
