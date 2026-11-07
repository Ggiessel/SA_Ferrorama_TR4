# Roteiro de testes — Ferrorama TR4

Teste em dupla: um opera, o outro anota. Data da execução: ____/____/______. Responsáveis: ______________________

| Nº | O que testar | Resultado esperado | Passou? | Observação |
|---|---|---|---|---|
| 1 | Importar `banco-de-dados/ferrorama_tr4.sql` em banco vazio | Cria o banco e todas as tabelas, sem erro | ( ) sim ( ) não | |
| 2 | Abrir `http://localhost/SA_Ferrorama_TR4` | Tela de login aparece | ( ) sim ( ) não | |
| 3 | Entrar com senha errada | Mensagem "E-mail ou senha incorretos.", sem entrar | ( ) sim ( ) não | Antes a mensagem dizia se o e-mail existia; falhou e foi corrigido |
| 4 | Entrar com e-mail que não existe | Mesma mensagem do teste 3 | ( ) sim ( ) não | |
| 5 | Entrar com `gestor@tr4.com` / `123456` | Entra e vai para o menu do gestor | ( ) sim ( ) não | |
| 6 | Conferir o topo da tela | Mostra "Conectado como" com o nome e o perfil | ( ) sim ( ) não | Antes o nome só aparecia nos menus; falhou e foi corrigido |
| 7 | Abrir `paginas/gestao-geral.html` em janela anônima | Redireciona para o login | ( ) sim ( ) não | |
| 8 | Entrar como cliente e digitar o endereço `paginas/cadastro-carga.html` | Volta para o menu do cliente, sem acesso | ( ) sim ( ) não | |
| 9 | Criar uma conta nova pela tela de cadastro | A conta entra como Cliente, sem escolher perfil | ( ) sim ( ) não | Antes qualquer pessoa podia virar gestor; falhou e foi corrigido |
| 10 | Cadastrar uma carga com campo vazio | Mensagem no campo, sem gravar | ( ) sim ( ) não | |
| 11 | Cadastrar uma carga completa | Aparece em Monitoramento de Cargas | ( ) sim ( ) não | |
| 12 | Registrar manutenção com código de trem inexistente | Mensagem de erro, sem gravar | ( ) sim ( ) não | |
| 13 | Adicionar um sensor | Aparece na lista com "Instalação até" a data prevista | ( ) sim ( ) não | |
| 14 | Apagar um alerta como gestor | Pede confirmação e o alerta some | ( ) sim ( ) não | |
| 15 | Olhar a coluna `senha` da tabela `usuarios` no phpMyAdmin | Texto embaralhado começando com `$2y$`, não legível | ( ) sim ( ) não | |
| 16 | Sair e apertar o botão voltar | Não volta para dentro do sistema | ( ) sim ( ) não | |
| 17 | Imprimir o relatório | A impressão sai sem menu nem botões | ( ) sim ( ) não | |
| 18 | Instalação limpa: copiar o projeto para uma pasta nova e seguir só o README | Sistema abre e o login funciona | ( ) sim ( ) não | |

## Itens dos guias que não podem ser testados

Cadastrar, editar e excluir trens; escolher o trem em lista ao cadastrar sensor; simulador de leituras; alertas por severidade; exportação em CSV; desativação de usuários e recuperação de senha. Esses itens não foram implementados e estão registrados nas limitações do README.
