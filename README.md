# Ferrorama TR4 — Sistema de Gerenciamento Ferroviário

## Equipe

- Matheus de Souza Salem Bello
- Gabriel Giessel de Oliveira
- João Pedro Santoro Pereira
- Thiago Henrique de Souza Cavallieri

Turma DS24 M3 — Escola SESI de Referência Joinville Moinho — Desenvolvimento de Sistemas — Projeto de Mapeamento de Ferrovias.

## Objetivo

O TR4 reúne em um só lugar a operação de uma ferrovia: trens e sua situação operacional, sensores IoT instalados nos trens e suas leituras, rotas, horários e viagens, cargas, manutenções, alertas com gravidade (Normal, Atenção, Crítico), um painel com indicadores e relatórios com exportação CSV e impressão.

Cada pessoa entra com a própria conta e vê apenas o que o seu perfil permite. As permissões são verificadas no servidor, em todas as APIs.

## Tecnologias

- **PHP 8** (APIs em `api/`, respostas JSON) com **PDO** e consultas preparadas
- **MySQL / MariaDB** (XAMPP)
- **HTML, CSS e JavaScript** (páginas em `paginas/`, scripts em `js/`, consumo das APIs com `fetch`)
- **Bootstrap 5.3.3** e **Bootstrap Icons 1.13.1**, servidos localmente em `vendor/` (funciona sem internet)
- Fonte Inter (Google Fonts; sem internet o navegador usa a fonte do sistema)

### Estrutura

| Pasta / arquivo | Conteúdo |
|---|---|
| `paginas/` | Telas (HTML) |
| `js/` | `global.js` (menu lateral, cabeçalho, chamadas à API, formatação) e um script por tela |
| `css/ferrorama-bootstrap.css` | Identidade visual do TR4 |
| `api/` | APIs PHP. `conexao.php` tem a conexão, a sessão e as funções de permissão |
| `includes/limites.php` | Limites dos sensores e classificação Normal / Atenção / Crítico |
| `includes/relatorios.php` | Cálculo dos quatro relatórios |
| `banco-de-dados/ferrorama_tr4.sql` | Banco completo com dados de exemplo |
| `banco-de-dados/migracao-v2.sql` | Atualiza um banco da versão anterior sem perder dados |
| `testes/` | Testes automatizados da API e da interface e seus resultados |
| `docs/manual-do-usuario.pdf` | Manual do usuário com capturas reais |

## Como configurar o XAMPP

1. Instale o XAMPP (Apache + PHP 8 + MySQL/MariaDB).
2. Copie a pasta do projeto para `C:\xampp\htdocs\SA_Ferrorama_TR4` (o nome da pasta pode ser outro; o sistema usa apenas caminhos relativos).
3. No painel do XAMPP, clique em **Start** no **Apache** e no **MySQL**.
4. Se o seu MySQL tiver senha ou outro usuário, altere `$usuarioBanco` e `$senhaBanco` em `api/conexao.php` (e em `testes/testes-api.php`, se for rodar os testes).

## Como criar o banco de dados

**Instalação nova:** abra `http://localhost/phpmyadmin`, aba **Importar**, escolha `banco-de-dados/ferrorama_tr4.sql` e clique em **Importar**. O script apaga e recria o banco `ferrorama_tr4` com dados de exemplo (8 trens, 20 sensores — um ativo sem leituras e um em instalação —, cerca de 1.800 leituras dos últimos 29 dias, 67 viagens, 6 manutenções, alertas dos três níveis e 10 usuários).

Pela linha de comando: `C:\xampp\mysql\bin\mysql -u root < banco-de-dados\ferrorama_tr4.sql`

**Banco da versão anterior com dados que você quer manter:** faça um backup (phpMyAdmin > Exportar) e importe `banco-de-dados/migracao-v2.sql`. Ele adiciona as colunas e tabelas novas, transforma os funcionários em usuários (senha inicial 123456, com troca obrigatória), converte as leituras antigas (uma linha por trem) em leituras por sensor e converte os tipos de alerta em níveis.

As datas dos dados de exemplo são relativas ao momento da importação. Reimporte o arquivo para "renovar" os dados.

## Como executar

Acesse `http://localhost/SA_Ferrorama_TR4` (sempre por `http://`, nunca abrindo o arquivo com duplo clique). A página inicial leva ao login.

## Usuários de teste

Senha de todos: **123456**

| Login | Perfil | Observação |
|---|---|---|
| gestor@tr4.com | Gestor (administrador) | Acesso total |
| ana.oliveira@tr4.com | Gestor | Segundo gestor |
| maquinista@tr4.com | Maquinista | Conduz o Trem 08 |
| antonio.pereira@tr4.com | Maquinista | Conduz o Trem 11 |
| carlos.silva@tr4.com | Maquinista | Conduz o Trem 13 |
| cliente@tr4.com | Cliente (usuário comum) | |
| paulo.alves@tr4.com | Maquinista | **Desativado** — serve para testar o bloqueio de login |

## Perfis e permissões

| Funcionalidade | Gestor | Maquinista | Cliente |
|---|---|---|---|
| Painel (5 indicadores) | Frota inteira | Alertas, sensores e velocidade do seu trem | — |
| Trens | Listar, cadastrar, editar, alterar situação, excluir | Consulta do próprio trem | — |
| Sensores | Cadastrar, editar, excluir/desativar, consultar | Consulta dos sensores do seu trem | — |
| Leituras | Consultar, filtrar, registrar, exportar CSV | Do seu trem | — |
| Simulador | Sim | — | — |
| Rotas e viagens | Cadastro, edição e filtros | Consulta; inicia, conclui e informa atraso das viagens do seu trem | Rotas ativas, horários e viagens |
| Cargas | Cadastrar e atualizar andamento | Consulta | Consulta |
| Manutenção | Registrar e atualizar (situação, custo, conclusão) | Registrar e consultar do seu trem | — |
| Alertas | Todos; criar, resolver, reabrir e apagar | Do seu trem e avisos públicos; criar alerta do seu trem | Somente avisos públicos |
| Relatórios e CSV | Toda a frota | Do seu trem | — |
| Usuários | Cadastrar, editar, alterar perfil, desativar/reativar, redefinir senha | — | — |

Regras de segurança implementadas no servidor:

- Senhas com `password_hash` (bcrypt) e `password_verify`; mensagem única para e-mail inexistente e senha errada.
- Toda API chama `exigirCargo()`; o usuário é **relido do banco a cada requisição**, então uma desativação ou mudança de perfil vale imediatamente (a sessão do desativado é encerrada).
- Para o maquinista, o filtro pelo próprio trem é aplicado **no SQL** (`restricaoTrem` / `exigirAcessoTrem`), mesmo que ele altere a URL ou os parâmetros.
- Usuário desativado não entra; o gestor não pode desativar a própria conta nem tirar o próprio perfil de gestor, e o sistema não permite desativar ou rebaixar o último gestor ativo.
- Cookie de sessão `HttpOnly` e `SameSite=Lax`, sessão expira após 2 horas sem uso, `session_regenerate_id` no login.
- Alterações (POST/PUT/PATCH/DELETE) exigem o cabeçalho `X-Requested-With: fetch`, que formulários de outros sites não conseguem enviar (proteção CSRF).
- `includes/` e `banco-de-dados/` têm `.htaccess` que bloqueia o acesso pelo navegador.
- O cadastro público (tela "Cadastre-se") sempre cria um cliente; o perfil não pode ser escolhido pelo usuário.

## Recuperação e redefinição de senha

Como o XAMPP local não envia e-mails, a recuperação funciona assim: na tela de login, **Esqueci minha senha** registra o pedido e gera um alerta para o gestor (a resposta é a mesma para qualquer e-mail, para não revelar quem tem conta). O gestor, em **Usuários**, clica na chave e o sistema gera uma **senha temporária**, exibida uma única vez. No próximo acesso o usuário é levado à tela **Meu perfil** e só continua depois de criar uma nova senha. Qualquer usuário também pode trocar a própria senha em **Meu perfil**, informando a senha atual.

## Sensores, leituras e limites

Cada leitura pertence a um **sensor** e a um **trem** (`leituras.sensor_id`, `leituras.trem_id`) e guarda o valor e o nível. A "última leitura" de cada sensor é calculada pela leitura mais recente no banco (maior data e, no empate, maior id); sensores sem leitura continuam na lista com "Sem leituras".

Os limites ficam em `includes/limites.php`:

| Grandeza | Atenção | Crítico |
|---|---|---|
| Velocidade | > 70 km/h | > 80 km/h |
| Temperatura do motor | > 95 °C | > 105 °C |
| Consumo de combustível | > 65 L/h | > 75 L/h |
| Vibração | > 5,5 mm/s | > 7 mm/s |
| Temperatura dos gases de escape | > 480 °C | > 550 °C |
| Temperatura do rolamento | > 70 °C | > 85 °C |
| Pressão do freio | < 420 kPa | < 350 kPa |
| Temperatura da carga | > 30 °C | > 35 °C |

Os limites críticos de velocidade, temperatura, consumo e vibração são os da referência técnica do projeto. Uma leitura de Atenção ou Crítico cria (ou atualiza) um alerta ativo ligado ao sensor, ao trem e à leitura; um sensor nunca tem dois alertas ativos ao mesmo tempo.

**Simulador:** gera de 1 a 100 leituras por sensor ativo do trem escolhido (uma a cada 5 minutos, terminando agora), com valores sorteados dentro de uma faixa plausível para cada grandeza (cerca de 80% normais, 15% atenção e 5% críticos). As leituras são gravadas na tabela `leituras` e passam pela mesma classificação e geração de alertas das leituras reais.

**Dispositivo real:** um ESP32 (ou outro) pode enviar `POST api/leituras.php` com `{"sensor_id": 3, "valor": 72.5}`, ou no formato antigo `{"trem_id": 1, "velocidade_kmh": ..., "temperatura_motor_c": ..., "consumo_litros_hora": ..., "vibracao_mm_s": ...}` — com sessão de gestor ou do maquinista do trem e o cabeçalho `X-Requested-With: fetch`.

## Painel

Os cinco indicadores vêm de `api/painel.php`, calculados no banco: (1) trens por situação operacional, (2) alertas ativos por gravidade, (3) sensores cuja leitura mais recente é crítica, (4) viagens em andamento (inclui as atrasadas) e (5) velocidade média da frota nas últimas 24 horas. Sem leituras no período, a média aparece como "—", nunca como zero inventado.

## Relatórios

Em **Relatórios** há quatro abas, todas com período (a data final é **inclusiva**: vai até 23:59:59), filtro por trem, **Exportar CSV** e **Imprimir**:

1. **Desempenho operacional** — viagens, concluídas, canceladas, pontualidade (chegada até 15 min após o previsto), atraso médio, km percorridos, velocidade média e máxima.
2. **Consumo de energia (combustível)** — leituras de consumo, consumo médio e máximo (L/h), horas em viagem e combustível estimado (consumo médio × horas).
3. **Eficiência operacional** — km/L real × nominal, utilização da frota (horas em viagem ÷ horas do período), % de leituras normais, leituras críticas e viagens canceladas.
4. **Manutenção** — manutenções do período com tipo, situação, custo, conclusão e dias parado; filtros por situação e tipo.

O CSV (`api/exportar.php`) sai em UTF-8 com BOM, separado por ponto e vírgula, com vírgula decimal e sem separador de milhar — abre direto no Excel em português com os acentos corretos. Nenhum HTML é misturado ao arquivo; se houver erro (sessão expirada, data inválida), a tela mostra a mensagem em vez de baixar um arquivo.

A impressão usa CSS próprio: esconde menu, filtros e botões, imprime em A4 paisagem e acrescenta um cabeçalho com título, período, trem, data e autor.

## Interface

- Menu lateral fixo no desktop e menu deslizante (botão ☰) no celular, com itens conforme o perfil.
- Cabeçalho com o título da tela e o usuário conectado (nome, perfil e trem).
- Cada módulo tem sua listagem e sua ação de cadastro (botão "Novo ...") dentro do próprio módulo; o menu não tem mais cadastros soltos.
- Telas abaixo de 1200 px de largura mostram as tabelas como cartões (rótulo + valor), sem rolagem horizontal.
- Busca: funciona em Trens, Sensores, Rotas, Alertas e Usuários (a busca do menu, que só escondia botões, foi removida).

## Testes

Resultados registrados (nome, esperado, obtido, status e data) em:

- `testes/resultado-testes-api.md` — 58 testes da API (login, logout, permissões, cadastro, edição, desativação, trens, sensores, leituras, simulador, rotas, viagens, alertas, painel, relatórios, CSV, usuários, manutenção);
- `testes/resultado-testes-interface.md` — 16 testes no navegador (login, logout, menus por perfil, formulários, alertas críticos em vermelho, painel, CSV, impressão e responsividade no celular e no desktop);
- `roteiro-de-testes.md` — resumo e roteiro manual.

Para executar de novo (com o banco recém-importado, pois os testes alteram dados):

```
php testes/testes-api.php http://localhost/SA_Ferrorama_TR4
node testes/testes-interface.js http://localhost/SA_Ferrorama_TR4
```

O segundo exige Node.js e o Playwright (`npm i -D playwright` e `npx playwright install chromium`).

## Banco de dados

Tabelas: `usuarios`, `trens`, `rotas`, `horarios`, `viagens`, `cargas`, `sensores`, `leituras`, `manutencoes`, `alertas`.

Principais relacionamentos e decisões:

- `leituras.sensor_id → sensores` e `leituras.trem_id → trens` (índices por sensor+data, trem+data e data).
- `sensores.trem_id → trens` (`ON DELETE CASCADE`). Excluir um sensor que já tem leituras apenas o **desativa**, para preservar o histórico.
- `viagens` liga `rotas`, `trens` e o `usuarios` maquinista no tempo; substituiu a antiga tabela `linhas`, que só guardava um texto de status fixo.
- `usuarios.trem_id` indica o trem do maquinista. A antiga tabela `funcionarios` (sem login) foi unificada em `usuarios`.
- `alertas` tem `nivel`, `situacao` (ativo/resolvido), `origem` (sensor/manual/sistema), `publico` e chaves para trem, sensor, leitura e usuário.
- `manutencoes.rota_id` passou a ser opcional e ganhou `tipo`, `status`, `custo` e `data_conclusao`.
- Trens e rotas com viagens ou manutenções não podem ser excluídos (use a situação Inativo / rota inativa).

## Limitações e pendências

- A recuperação de senha depende do gestor (não há envio de e-mail no XAMPP local).
- As páginas são HTML estático: a tela chega a abrir por um instante antes de o JavaScript redirecionar quem não tem permissão, mas nenhum dado é entregue sem sessão e perfil, porque a verificação é feita nas APIs.
- Os testes foram executados com PHP 8.3 e MariaDB 10.11 no servidor embutido do PHP (`php -S`). A proteção por `.htaccess` das pastas `includes/` e `banco-de-dados/` só vale no Apache; ela deve ser conferida no XAMPP.
- O mapa do painel é uma imagem ilustrativa (não há geolocalização em tempo real).
- O consumo de energia é estimado a partir das leituras de consumo e das horas de viagem; não há medição direta de litros abastecidos.

## Uso de inteligência artificial

| Etapa | Ferramenta | O que a IA fez | O que a equipe fez |
|---|---|---|---|
| Planejamento e design | — | — | Definiu o problema, os perfis e as telas; criou os mockups no Figma |
| Implementação (HTML, JS, PHP, SQL) | Claude Code (Anthropic) | Em 17/11/2026, auditou o repositório, reestruturou o banco (leituras por sensor, viagens, níveis de alerta, usuários), reescreveu as APIs e as telas, criou os relatórios, o painel e os testes automatizados, e produziu o manual com capturas do sistema | Redigiu a especificação completa usada na reestruturação (perfis, regras de segurança, indicadores, relatórios e critérios de entrega) |
| CSS e ajuste visual aos mockups | Claude (Anthropic) | Escreveu e reorganizou o CSS para seguir os mockups e a devolutiva | Comparou com os mockups e pediu os ajustes |
| Instalação e integração | — | — | Configurou o XAMPP, importou o banco e resolveu os conflitos no Git |
| Conferência com os guias | Claude (Anthropic) | Apontou falhas de segurança, corrigiu e rascunhou este README | Revisou as correções, executou o roteiro de testes e completou o README |
