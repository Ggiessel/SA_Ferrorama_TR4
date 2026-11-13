# Ferrorama TR4 — Sistema de Monitoramento Ferroviário

## Equipe

- Matheus de Souza Salem Bello
- Gabriel Giessel de Oliveira
- João Pedro Santoro Pereira
- Thiago Henrique de Souza Cavallieri

Turma DS24 M3 — Escola SESI de Referência Joinville Moinho — Desenvolvimento de Sistemas — Projeto de Mapeamento de Ferrovias.

## O que o sistema faz

O TR4 reúne em um só lugar as informações de operação de uma ferrovia: situação dos trens, horários e status das rotas, cargas em transporte, sensores IoT instalados nos trens, leituras desses sensores, manutenções e alertas.

Cada pessoa entra com a própria conta e vê apenas o que o seu perfil permite. O gestor acompanha a operação inteira, cadastra trens, rotas, cargas e sensores, gera leituras pelo simulador e consulta os relatórios. O maquinista acompanha rotas, cargas e registra manutenções. O cliente consulta as informações públicas: painel, rotas, cargas e alertas.

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
| `paginas/gestao-geral.html` | Painel: mapa, trens por status, dados por rota e por trem, resumo dos sensores por trem, velocidade média e últimos alertas dos sensores | Todos |
| `paginas/gestao-rotas.html` | Horários, status das linhas e alerta de cada rota | Todos |
| `paginas/monitoramento.html` | Cargas em transporte, com filtro por status | Todos |
| `paginas/alertas.html` | Alertas e notificações, com busca | Todos |
| `paginas/perfil-pessoal.html` | Dados da própria conta | Todos |
| `paginas/manutencao.html` | Registro de manutenção de trem | Gestor e maquinista |
| `paginas/relatorios.html` | Resumo, cargas, manutenções e leituras dos sensores com filtros por trem, situação e período; impressão e exportação CSV | Gestor e maquinista |
| `paginas/cadastro-carga.html` | Cadastro de cargas | Gestor |
| `paginas/gestao-sensores.html` | Lista de sensores IoT por trem | Gestor |
| `paginas/adicionar-sensor.html` | Instalação de sensor em um trem | Gestor |
| `paginas/remover-sensor.html` | Remoção de sensor | Gestor |
| `paginas/leituras.html` | Leituras dos sensores, com filtro por trem, filtro de falhas e exportação CSV | Gestor |
| `paginas/simulador.html` | Simulador de leituras dos sensores | Gestor |
| `paginas/adicionar-trem.html` | Cadastro, edição, listagem e exclusão de trens | Gestor |
| `paginas/adicionar-rota.html` | Cadastro, edição, listagem e exclusão de rotas | Gestor |
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

**Sensores IoT ligados ao trem.** Cada sensor fica instalado em um trem (`sensores.trem_id`) e é um modelo real usado em telemetria: DS18B20 (temperatura do motor), MAX6675 (temperatura dos gases de escape), MLX90614 (temperatura do rolamento), ADXL345 e MPU-6050 (vibração), A3144 (velocidade pela roda), NEO-6M (GPS), Omnicomm LLS 5 (combustível), MPX5700AP (pressão do freio) e DHT22 (temperatura e umidade da carga). A lista fica no servidor (`tiposSensor()` em `api/conexao.php`), então só esses modelos são aceitos. Remover um sensor não apaga na hora: ele fica marcado para remoção com prazo de 4 semanas.

**Leituras e limites.** A tabela `leituras` guarda velocidade (km/h), temperatura do motor (°C), consumo (L/h) e vibração (mm/s) de cada trem, com data e hora. Os limites ficam em um só lugar (`api/funcoes-leituras.php`): 80 km/h, 105 °C, 75 L/h e 7 mm/s. A função `classificarLeitura` compara cada valor com o limite e devolve a lista de falhas. Uma leitura com falha aparece em vermelho e gera um alerta na tela de Alertas.

**Simulador.** Enquanto os sensores físicos não enviam dados, o simulador gera de 1 a 200 leituras para um trem, uma a cada cinco minutos, terminando no horário atual. Um dispositivo real (por exemplo, um ESP32 com os sensores acima) pode enviar uma leitura com `POST` para `api/leituras.php`, com os quatro valores e o `trem_id`.

**Trens e rotas.** O gestor cadastra, edita e exclui trens e rotas pelas telas Adicionar Trens e Adicionar Rotas. Um trem "em rota" precisa ter uma rota. Excluir um trem apaga junto suas leituras e seus sensores (`ON DELETE CASCADE`). Excluir uma rota deixa os trens dela como disponíveis. Trens e rotas com manutenções registradas não podem ser excluídos, para não perder o histórico. A exclusão sempre pede confirmação.

**Relatório e CSV.** O relatório de leituras filtra por trem, situação do trem e período, e mostra total de leituras, leituras com alerta, velocidade média e temperatura média. O mesmo filtro gera o arquivo CSV (`api/exportar-leituras.php`), separado por ponto e vírgula para abrir direto no Excel.

**Alertas em uma tabela só.** Alertas e notificações ficam na tabela `alertas`, com tipo `info`, `aviso` ou `atraso`. Alguns são gerados automaticamente, como quando uma carga é entregue. Só o gestor pode apagar alertas, com confirmação.

**Gráfico sem biblioteca.** O gráfico de trens por status é desenhado com `<canvas>` em JavaScript, sem biblioteca, e funciona offline. As cores seguem a convenção semântica: verde para em rota, verde-claro para disponível e amarelo para em manutenção.

**Proteção das páginas.** As páginas são HTML estático. Ao abrir, cada página consulta `api/usuario.php`: sem sessão, redireciona para o login, e com perfil não autorizado, volta ao menu do usuário. Os dados nunca são entregues sem sessão, porque a verificação é feita na API.

## Limitações conhecidas

- **Arquitetura diferente da dos guias.** As telas são HTML com JavaScript consumindo uma API em PHP com PDO, e não páginas PHP geradas pelo servidor com mysqli, `proteger.php` e `cabecalho.php`. Os nomes de arquivo não seguem os nomes canônicos. A página HTML chega a abrir por um instante antes de o JavaScript redirecionar quem não está logado, mas nenhum dado é exibido sem sessão.
- **Sensores e leituras:** as leituras guardam as quatro grandezas fixas (velocidade, temperatura do motor, consumo e vibração). Os demais sensores cadastrados (pressão do freio, rolamento, carga) ainda não têm leituras próprias.
- **Rotas:** não há filtros de rotas por data e trem.
- **Alertas:** os alertas não têm os três níveis de severidade (normal, atenção, crítico).
- **Gestão de usuários:** não há desativação de usuários nem recuperação de senha. A mudança de perfil de um usuário só pode ser feita direto no banco, pelo phpMyAdmin.
- **Vocabulário:** a tela de funcionários usa o termo "funcionários" e uma tabela separada de `usuarios`.
- **Celular:** o layout foi pensado para desktop. As tabelas rolam na horizontal no celular, sem virar cartões.

## Uso de inteligência artificial

| Etapa | Ferramenta | O que a IA fez | O que a equipe fez |
|---|---|---|---|
| Planejamento e design | — | — | Definiu o problema, os perfis e as telas; criou os mockups no Figma |
| Implementação (HTML, JS, PHP, SQL) | [completar] | [completar] | [completar] |
| CSS e ajuste visual aos mockups | Claude (Anthropic) | Escreveu e reorganizou o CSS para seguir os mockups e a devolutiva | Comparou com os mockups e pediu os ajustes |
| Instalação e integração | — | — | Configurou o XAMPP, importou o banco e resolveu os conflitos no Git |
| Conferência com os guias | Claude (Anthropic) | Apontou falhas de segurança, corrigiu e rascunhou este README | Revisou as correções, executou o roteiro de testes e completou o README |
