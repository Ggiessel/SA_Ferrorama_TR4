# Resultado dos testes automatizados da API

Executado em 17/11/2026 09:57 com `php testes/testes-api.php`. 58 de 58 aprovados.

| Nº | Área | Teste | Resultado esperado | Resultado obtido | Status | Data |
|---|---|---|---|---|---|---|
| 1 | Login | Login com senha errada é recusado | 401 e mensagem genérica | 401 - E-mail ou senha incorretos. | Aprovado | 17/11/2026 09:57 |
| 2 | Login | Login com e-mail inexistente dá a mesma mensagem | 401 e a mesma mensagem do teste anterior | 401 - E-mail ou senha incorretos. | Aprovado | 17/11/2026 09:57 |
| 3 | Login | Login do gestor com dados corretos | 200 e perfil gestor | 200 - perfil gestor | Aprovado | 17/11/2026 09:57 |
| 4 | Login | Senhas gravadas com hash bcrypt | Todas as senhas começam com $2y$ | 0 senhas sem hash | Aprovado | 17/11/2026 09:57 |
| 5 | Login | Usuário desativado não consegue entrar | 403 com aviso de conta desativada | 403 - Sua conta está desativada. Procure o gestor do sistema. | Aprovado | 17/11/2026 09:57 |
| 6 | Permissões | API sem sessão é bloqueada | 401 no painel | 401 | Aprovado | 17/11/2026 09:57 |
| 7 | Segurança | Alteração sem cabeçalho X-Requested-With (CSRF) é recusada | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 8 | Cadastro | Cadastro público sempre cria cliente (ignora cargo enviado) | 201 e perfil cliente | 201 - perfil cliente | Aprovado | 17/11/2026 09:57 |
| 9 | Permissões | Cliente não acessa leituras | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 10 | Permissões | Cliente não acessa o painel nem relatórios | 403 nos dois | painel 403, relatórios 403 | Aprovado | 17/11/2026 09:57 |
| 11 | Permissões | Cliente vê somente avisos públicos | Nenhum alerta com publico = 0 | 4 avisos, 0 privados | Aprovado | 17/11/2026 09:57 |
| 12 | Permissões | Maquinista não cadastra trem (requisição direta) | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 13 | Permissões | Maquinista não exclui sensor | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 14 | Permissões | Maquinista lista apenas o próprio trem | 1 trem (Trem 08) | 1 trem(ns): Trem 08 | Aprovado | 17/11/2026 09:57 |
| 15 | Permissões | Maquinista não abre trem de outro maquinista pela URL | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 16 | Permissões | Maquinista não vê leituras de outro trem mesmo filtrando | 0 leituras de outros trens | 0 leituras, 0 de outros trens | Aprovado | 17/11/2026 09:57 |
| 17 | Permissões | Maquinista não usa o simulador | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 18 | Permissões | Maquinista não acessa a administração de usuários | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 19 | Permissões | Usuário não altera o próprio perfil pela API do perfil | Perfil continua cliente | perfil no banco: cliente | Aprovado | 17/11/2026 09:57 |
| 20 | Trens | Cadastrar trem completo | 201 e trem gravado no banco | 201 - código gravado 090 | Aprovado | 17/11/2026 09:57 |
| 21 | Trens | Código de trem repetido é recusado | 400 com mensagem no campo codigoTrem | 400 - Já existe um trem com o código 008. | Aprovado | 17/11/2026 09:57 |
| 22 | Trens | Editar trem | Modelo alterado no banco | 200 - modelo EMD SD70ACe | Aprovado | 17/11/2026 09:57 |
| 23 | Trens | Situação 'em rota' exige rota | 400 no campo rotaTrem | 400 - Escolha a rota (ativa) em que o trem está. | Aprovado | 17/11/2026 09:57 |
| 24 | Trens | Alterar situação operacional | Trem fica inativo | 200 - inativo | Aprovado | 17/11/2026 09:57 |
| 25 | Sensores | Cadastrar sensor ativo e associar ao trem | 201 e sensor ligado ao trem | 201 - chave temperatura_motor | Aprovado | 17/11/2026 09:57 |
| 26 | Sensores | Sensor sem leituras aparece na lista sem valor | ultimo_valor nulo e 0 leituras | valor NULL, 0 leituras | Aprovado | 17/11/2026 09:57 |
| 27 | Sensores | Sensor repetido no mesmo trem é recusado | 400 | 400 - O Trem 90 já tem um sensor DS18B20 ativo ou em instalação. | Aprovado | 17/11/2026 09:57 |
| 28 | Sensores | Editar sensor (observação) | Observação gravada | 200 | Aprovado | 17/11/2026 09:57 |
| 29 | Leituras | Leitura crítica gera alerta crítico ligado ao sensor e ao trem | nível crítico e 1 alerta ativo do sensor | nível critico, 1 alerta(s) | Aprovado | 17/11/2026 09:57 |
| 30 | Leituras | Leitura de atenção e normal classificadas pelos limites | atencao para 100 °C e normal para 80 °C | atencao / normal | Aprovado | 17/11/2026 09:57 |
| 31 | Leituras | Última leitura do sensor vem do registro mais recente | Valor 80 (a última gravada) | API 80.00, banco 80.00 | Aprovado | 17/11/2026 09:57 |
| 32 | Leituras | Valor não numérico é recusado | 400 | 400 | Aprovado | 17/11/2026 09:57 |
| 33 | Simulador | Simulador grava as leituras no banco | Quantidade gravada = quantidade informada | geradas 20, diferença no banco 20 | Aprovado | 17/11/2026 09:57 |
| 34 | Leituras | Filtro por data final é inclusivo | Leitura de hoje 23:59 incluída com data_final = hoje | 1 leitura das 23:59:30 encontrada(s) | Aprovado | 17/11/2026 09:57 |
| 35 | Leituras | Data inválida no filtro é recusada | 400 | 400 | Aprovado | 17/11/2026 09:57 |
| 36 | Sensores | Excluir sensor com leituras apenas desativa (mantém histórico) | Sensor inativo e leituras preservadas | situação inativo, 4 leituras | Aprovado | 17/11/2026 09:57 |
| 37 | Rotas | Horário inválido (25:00) é recusado | 400 no campo horariosRota | 400 - O horário 25:00 não é válido. Use o formato 07:30. | Aprovado | 17/11/2026 09:57 |
| 38 | Rotas | Cadastrar e editar rota | Rota criada e aviso atualizado | criação 201, edição 200, 1 horário(s) | Aprovado | 17/11/2026 09:57 |
| 39 | Rotas | Filtro de viagens por data, situação, trem e rota bate com o banco | Mesma contagem da consulta SQL | API 4, banco 4 | Aprovado | 17/11/2026 09:57 |
| 40 | Rotas | Agendar, iniciar e concluir viagem atualiza o trem | Trem em rota ao iniciar e disponível ao concluir | durante em_rota, depois disponivel, viagem concluida | Aprovado | 17/11/2026 09:57 |
| 41 | Permissões | Maquinista não conclui viagem de outro trem | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 42 | Alertas | Maquinista gera alerta do próprio trem | 201 e alerta gravado com o trem 1 | 201 - trem 1 | Aprovado | 17/11/2026 09:57 |
| 43 | Alertas | Cliente não gera alerta | 403 | 403 | Aprovado | 17/11/2026 09:57 |
| 44 | Alertas | Gestor resolve alerta e filtro por gravidade funciona | Alerta resolvido; filtro crítico só traz críticos | situação resolvido, 13 críticos, 0 de outro nível | Aprovado | 17/11/2026 09:57 |
| 45 | Dashboard | Indicadores do painel batem com o banco | Trens, alertas, sensores críticos, viagens e velocidade iguais ao SQL | em rota 4/4, críticos 6/6, viagens 4/4, sensores 2/2, velocidade 58.3/58.3 | Aprovado | 17/11/2026 09:57 |
| 46 | Relatórios | Os quatro relatórios respondem com dados do período | 200 e linhas nos 4 relatórios | {"desempenho":9,"energia":9,"eficiencia":9,"manutencao":5} | Aprovado | 17/11/2026 09:57 |
| 47 | Relatórios | Período sem dados não gera erro e médias ficam vazias | 200 e médias nulas (sem divisão por zero) | 200 - 0 médias preenchidas | Aprovado | 17/11/2026 09:57 |
| 48 | Relatórios | Maquinista vê relatório somente do próprio trem | Apenas Trem 08 | Trem 08 | Aprovado | 17/11/2026 09:57 |
| 49 | CSV | Exportação CSV sem HTML, com BOM UTF-8 e separador ; | text/csv, BOM, acentos e nenhum '<' | status 200, 6 linhas de dados | Aprovado | 17/11/2026 09:57 |
| 50 | CSV | CSV usa vírgula decimal e não tem separador de milhar | Custo 18500,00 | 18500,00 encontrado | Aprovado | 17/11/2026 09:57 |
| 51 | CSV | CSV sem sessão é bloqueado | 401 | 401 | Aprovado | 17/11/2026 09:57 |
| 52 | Usuários | Gestor não pode desativar a própria conta | 409 | 409 - Você não pode desativar a sua própria conta. | Aprovado | 17/11/2026 09:57 |
| 53 | Usuários | Gestor desativado perde a sessão; sistema nunca fica sem gestor ativo | Sessão do desativado cai (401); última gestora não se desativa nem se rebaixa (409/400) | desativar outro gestor 200, sessão dele 401, desativar última 409, rebaixar a si mesmo 400 | Aprovado | 17/11/2026 09:57 |
| 54 | Usuários | Cadastrar usuário, alterar perfil e desativar | Usuário maquinista vinculado ao trem e depois sem acesso | perfil maquinista, login antes 200, sessão após desativar 401, login depois 403 | Aprovado | 17/11/2026 09:57 |
| 55 | Usuários | Redefinir senha gera senha temporária com troca obrigatória | Senha temporária funciona e trocar_senha = 1 | login temporário 200, troca 200, flag final 0 | Aprovado | 17/11/2026 09:57 |
| 56 | Usuários | Pedido de recuperação não revela se o e-mail existe | Mesma resposta para e-mail existente e inexistente | respostas iguais: sim, alerta ao gestor: 1 | Aprovado | 17/11/2026 09:57 |
| 57 | Manutenção | Registrar e concluir manutenção atualiza a situação do trem | Em manutenção ao registrar; disponível ao concluir | durante manutencao, depois disponivel | Aprovado | 17/11/2026 09:57 |
| 58 | Logout | Logout encerra a sessão | Depois de sair, a API responde 401 | 401 | Aprovado | 17/11/2026 09:57 |
