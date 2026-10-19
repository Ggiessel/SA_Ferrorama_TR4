# Ferrorama TR4

## Como rodar
O site busca os dados no banco MySQL por meio de uma API em PHP. Tudo roda no **XAMPP**:

1. Coloque a pasta do projeto em `C:\xampp\htdocs\SA_Ferrorama_TR4` e ligue o **Apache** e o **MySQL**.
2. **Banco:** no phpMyAdmin (http://localhost/phpmyadmin), importe `banco-de-dados/ferrorama_tr4.sql`.
3. **Site:** abra http://localhost/SA_Ferrorama_TR4/

Não use o Live Server: ele não roda PHP.

O passo a passo completo e os erros mais comuns estão em `api/LEIA-ME.md`.
É preciso internet: Bootstrap, Bootstrap Icons e a fonte Inter vêm por CDN.

### Contas de teste (senha de todas: `123456`)
| E-mail | Cargo |
|---|---|
| gestor@tr4.com | Gestor (vê tudo) |
| maquinista@tr4.com | Maquinista |
| cliente@tr4.com | Cliente |

Também dá para criar uma conta nova em "Cadastre-se".

## Estrutura das pastas
```
api/            API em PHP que liga o site ao banco (veja api/LEIA-ME.md)
banco-de-dados/ ferrorama_tr4.sql -> script do banco MySQL
css/   ferrorama-bootstrap.css  -> tema único de todas as páginas (os outros .css antigos não são mais usados)
img/   logos, mapa e gráfico     -> pasta nova; antes as imagens ficavam dentro de paginas/
js/
  global.js                      -> funções usadas por todas as páginas (objeto TR4, chamadas à API)
  login.js, signin.js, selecao-usuario.js, menus.js
  alertas.js, cargas-rotas.js, gestao-geral.js, monitoramento.js
  manutencao.js, sensores.js, funcionarios.js, perfil.js, relatorios.js
paginas/  todas as telas .html
index.html  -> redireciona para o login
```

Cada página carrega, nesta ordem: `global.js` → script da página.

## Onde os dados ficam salvos
No banco MySQL `ferrorama_tr4`. As páginas pedem os dados para a API com `TR4.api()`
(no `global.js`), e a API consulta o banco.

- O login fica na sessão do PHP (um cookie que o navegador manda sozinho em todo pedido).
- A API confere a senha e as permissões de cada cargo, não o navegador.
- Para voltar aos dados originais: importe `banco-de-dados/ferrorama_tr4.sql` de novo.
- Alertas, troca do trem para "manutenção" e "carga entregue" são feitos pelos gatilhos (triggers) do banco.

## O que cada página faz
| Página | Script | Funcionalidades |
|---|---|---|
| login | login.js | valida e-mail e senha, mostra o erro em cada campo, leva ao menu do cargo |
| signin | signin.js | cria conta (nome, e-mail não repetido, senha com 6 ou mais caracteres) |
| selecao-usuario | selecao-usuario.js | salva o cargo e abre o menu certo |
| menus | menus.js | cada botão abre sua página; a pesquisa filtra os botões (Enter abre o 1º, Esc limpa); "Sair" |
| gestao-rotas | cargas-rotas.js | trocar a rota atualiza horários, status das linhas e alerta |
| cadastro-carga | cargas-rotas.js | listas preenchidas pelo JS, dias conforme o mês, valida e salva a carga |
| monitoramento | monitoramento.js | lista as cargas com barra de progresso, filtro por status, simula a viagem em tempo real |
| gestao-geral | gestao-geral.js | gráfico de trens desenhado em `<canvas>`, métricas por rota e por trem |
| manutencao | manutencao.js | confere o código do trem, salva, cria alerta; "Salvar como PDF" abre a impressão |
| gestao/adicionar/remover-sensor | sensores.js | lista os sensores, impede duplicados, prazo de 4 semanas |
| funcionarios / perfil-funcionario | funcionarios.js | lista com pesquisa; perfil com máscaras e validação de CPF, telefone e datas |
| perfil-pessoal | perfil.js | edita os dados de quem está logado |
| alertas | alertas.js | alertas gerados pelo sistema, pesquisa; o gestor pode apagar |
| relatorios | relatorios.js | resumo em números e tabelas de cargas e manutenções |

## Permissões
- **Gestor:** todas as páginas.
- **Maquinista:** gestão geral, rotas, monitoramento, manutenção, relatórios, alertas e perfil.
- **Cliente:** gestão geral, rotas, monitoramento, alertas e perfil.

Quem tenta abrir uma página sem permissão volta para o próprio menu (regras em `PERMISSOES`, no `global.js`).

## Página nova?
1. Copie o `<head>` de uma página existente e coloque uma classe `pg-...` no `<body>`.
2. No fim do `<body>`, carregue `global.js` e o script da página.
3. Coloque o código dentro de `TR4.iniciar(async function (usuario) { ... });` e busque os dados com `await TR4.api("/endereco")`.
4. Adicione a página em `PERMISSOES`, no `global.js`.
5. Crie o endpoint num arquivo `.php` em `api/src/endpoints/` (o `api/index.php` carrega todos sozinho).

## Tema CSS
- Breakpoints iguais aos do Bootstrap (576 / 768 / 992 / 1200 px).
- As cores ficam em `:root`, no topo do `ferrorama-bootstrap.css`. Mudou lá, muda no projeto todo.
