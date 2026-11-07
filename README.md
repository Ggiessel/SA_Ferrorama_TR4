# Ferrorama TR4 — Sistema de Monitoramento Ferroviário

## Equipe

Gabriel Giessel e [completar com os nomes dos demais integrantes] — Turma DS24 M3.
SESI/SENAI Joinville Moinho — Desenvolvimento de Sistemas — Projeto de Mapeamento de Ferrovias.

## O que o sistema faz

O TR4 reúne em um só lugar as informações de operação de uma ferrovia: situação dos trens, horários e status das rotas, cargas em transporte, sensores instalados, manutenções e alertas.

Cada pessoa entra com a própria conta e vê apenas o que o seu perfil permite. O gestor acompanha a operação inteira, cadastra cargas e sensores e consulta os relatórios. O maquinista acompanha rotas, cargas e registra manutenções. O cliente consulta as informações públicas: painel, rotas, cargas e alertas.

## Tecnologias

PHP, MySQL, HTML, CSS (com Bootstrap 5 e Bootstrap Icons por CDN) e JavaScript. Ambiente: XAMPP.

As telas são páginas HTML que buscam os dados com `fetch` em arquivos PHP da pasta `api/`, que respondem em JSON. A conexão com o banco usa PDO.

## Como instalar

1. Copiar a pasta do projeto para `C:\xampp\htdocs\SA_Ferrorama_TR4`.
2. Iniciar o Apache e o MySQL no painel do XAMPP.
3. Abrir `http://localhost/phpmyadmin`, aba **Importar**, e importar o arquivo `banco-de-dados/ferrorama_tr4.sql`. O script apaga e recria o banco `ferrorama_tr4` do zero.
4. Acessar `http://localhost/SA_Ferrorama_TR4` (sempre por `http://`, nunca abrindo o arquivo com duplo clique).

Se o MySQL tiver senha, ela é informada apenas em `$senhaBanco`, no arquivo `api/conexao.php`.

## Usuários de teste

| Login | Senha | Perfil |
|---|---|---|
| gestor@tr4.com | 123456 | Gestor |
| maquinista@tr4.com | 123456 | Maquinista |
| cliente@tr4.com | 123456 | Cliente |

## Telas do sistema

| Arquivo | O que faz | Perfis |
|---|---|---|
| `paginas/login.html` | Entrada no sistema | Todos |
| `paginas/signin.html` | Criação de conta (a conta nasce como cliente) | Público |
| `paginas/menu-gestor.html` | Menu do gestor | Gestor |
| `paginas/menu-maquinista.html` | Menu do maquinista | Maquinista |
| `paginas/menu.cliente.html` | Menu do cliente | Cliente |
| `paginas/gestao-geral.html` | Painel: mapa, trens por status, dados por rota e por trem | Todos |
| `paginas/gestao-rotas.html` | Horários, status das linhas e alerta de cada rota | Todos |
| `paginas/monitoramento.html` | Cargas em transporte, com filtro por status | Todos |
| `paginas/alertas.html` | Alertas e notificações, com busca | Todos |
| `paginas/perfil-pessoal.html` | Dados da própria conta | Todos |
| `paginas/manutencao.html` | Registro de manutenção de trem | Gestor e maquinista |
| `paginas/relatorios.html` | Resumo, cargas e manutenções, com impressão | Gestor e maquinista |
| `paginas/cadastro-carga.html` | Cadastro de cargas | Gestor |
| `paginas/gestao-sensores.html` | Lista de sensores | Gestor |
| `paginas/adicionar-sensor.html` | Instalação de sensor | Gestor |
| `paginas/remover-sensor.html` | Remoção de sensor | Gestor |
| `paginas/funcionarios.html` | Lista de funcionários, com busca | Gestor |
| `paginas/perfil-funcionario.html` | Edição dos dados de um funcionário | Gestor |

## Decisões tomadas

**Perfis e correspondência com o Guia de Uniformização.** O sistema usa três perfis: `gestor` corresponde a "administrador ou gerente", `maquinista` a "maquinista" e `cliente` a "usuário comum". O gestor é o perfil com acesso total.

**Papel no banco como ENUM.** A coluna `cargo` da tabela `usuarios` é `ENUM('gestor', 'maquinista', 'cliente')`, obrigatória, com padrão `cliente`. Assim o próprio banco recusa um papel inválido. A consequência é que criar um perfil novo exige alterar a tabela.

**Cadastro público cria apenas clientes.** Quem se cadastra pela tela de cadastro sempre recebe o perfil `cliente`, definido no servidor (`api/cadastro.php`). Nenhum usuário consegue escolher o próprio perfil. A tela "Escolha seu cargo" foi removida porque permitia que qualquer pessoa se tornasse gestor.

**Conexão com PDO.** Foi escolhido o PDO e ele é usado no projeto inteiro, só em `api/conexao.php`. Todas as consultas com dados do usuário usam `prepare` com `?` e os valores são enviados separados, pelas funções `consultar` e `executar`. A falha de conexão é tratada com `try/catch` e mostra uma mensagem em português.

**Senhas.** Gravadas com `password_hash(..., PASSWORD_DEFAULT)` e conferidas com `password_verify`. A coluna `senha` é `VARCHAR(255)`. O login mostra a mesma mensagem para e-mail inexistente e senha errada, para não revelar quais e-mails têm conta.

**Controle de acesso no servidor.** Cada arquivo da `api/` verifica a sessão e o perfil (`usuarioLogado` e `exigirCargo` em `api/conexao.php`) antes de ler ou gravar. Esconder botões e links na tela é só conforto: a regra que vale é a do PHP.

**Saída do sistema.** `api/sair.php` limpa (`session_unset`) e destrói (`session_destroy`) a sessão. Ao voltar pelo botão do navegador, a página é recarregada e, sem sessão, volta para o login.

**Unicidade.** `usuarios.email`, `trens.codigo` e `funcionarios.email` têm restrição `UNIQUE` no banco. O cadastro também verifica antes de gravar, para mostrar uma mensagem clara.

**ON DELETE.** Excluir uma rota exclui em cascata seus horários, linhas e sensores. Um trem tem a rota zerada (`SET NULL`) se a rota for excluída. Uma manutenção impede excluir o trem ou a rota a que se refere (padrão `RESTRICT`), para não perder histórico.

**Sensores ligados à rota.** O sensor foi modelado como instalado em um trecho (rota), e não em um trem. Remover um sensor não apaga na hora: ele fica marcado para remoção com prazo de 4 semanas.

**Alertas em uma tabela só.** Alertas e notificações ficam na tabela `alertas`, com tipo `info`, `aviso` ou `atraso`. Alguns são gerados automaticamente, como quando uma carga é entregue. Só o gestor pode apagar alertas, com confirmação.

**Gráfico sem biblioteca.** O gráfico de trens por status é desenhado com `<canvas>` em JavaScript, sem biblioteca, e funciona offline. As cores seguem a convenção semântica: verde para em rota, verde-claro para disponível e amarelo para em manutenção.

**Proteção das páginas.** As páginas são HTML estático. Ao abrir, cada página consulta `api/usuario.php`: sem sessão, redireciona para o login, e com perfil não autorizado, volta ao menu do usuário. Os dados nunca são entregues sem sessão, porque a verificação é feita na API.

## Limitações conhecidas

O projeto não seguiu toda a estrutura e todas as entregas definidas nos guias dos blocos 2, 3 e 4. Conforme a orientação do Fechamento, nada novo foi construído nesta etapa, e o que falta está registrado aqui:

- **Arquitetura diferente da dos guias.** As telas são HTML com JavaScript consumindo uma API em PHP, e não páginas PHP geradas pelo servidor com `proteger.php`, `cabecalho.php` e `permissao.php`. Os nomes de arquivo não seguem os nomes canônicos (`trens.php`, `sensores.php`, `rotas.php` e outros). A página HTML chega a abrir por um instante antes de o JavaScript redirecionar quem não está logado, mas nenhum dado é exibido sem sessão.
- **Gestão de trens:** não há tela para cadastrar, editar, buscar ou excluir trens. Os trens existem no banco e aparecem no painel.
- **Sensores:** estão ligados a rotas, e não a trens, e não têm edição.
- **Rotas:** só há consulta. Não há cadastro, edição, exclusão nem os filtros por data e trem.
- **Leituras, simulador e alertas por regra de severidade:** não foram implementados. Os alertas não têm severidade normal, atenção ou crítico.
- **Painel:** não mostra os cinco indicadores definidos no Bloco 4 nem diferencia o conteúdo por perfil.
- **Relatórios:** não há escolha de tipo e período nem exportação em CSV. Há impressão.
- **Gestão de usuários:** não há desativação de usuários, travas de proteção nem recuperação de senha. A mudança de perfil de um usuário só pode ser feita direto no banco, pelo phpMyAdmin.
- **Vocabulário:** a tela de funcionários usa o termo "funcionários" e uma tabela separada de `usuarios`, diferente do vocabulário padronizado ("usuários").
- **Celular:** o layout foi pensado para desktop. As tabelas do relatório rolam na horizontal no celular, sem virar cartões.

## Uso de inteligência artificial


