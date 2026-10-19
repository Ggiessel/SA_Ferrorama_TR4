# API do Ferrorama TR4 (PHP)

Liga o site ao banco MySQL `ferrorama_tr4`.
Feita em **PHP puro com PDO**: não precisa instalar nada além do XAMPP.

```
Navegador (site)  ──fetch──▶  Apache + PHP (api/index.php)  ──SQL──▶  MySQL (ferrorama_tr4)
```

## Passo a passo para rodar (XAMPP)

1. **Copie o projeto para o htdocs.** Coloque a pasta do projeto (a que tem `api`, `css`, `js`, `paginas`...)
   em `C:\xampp\htdocs\`, com o nome `SA_Ferrorama_TR4`.
   Dá para abrir essa pasta no VS Code e trabalhar direto nela.
2. **Ligue o Apache e o MySQL** no painel do XAMPP (botões *Start*).
3. **Crie o banco:** acesse http://localhost/phpmyadmin → aba **Importar** →
   escolha `banco-de-dados/ferrorama_tr4.sql` → **Executar**.
4. **Confira a conexão:** abra `api/config.php`. No XAMPP o padrão é usuário `root` sem senha
   (já está assim). Se o seu MySQL tiver senha, coloque em `DB_SENHA`.
5. **Teste a API:** http://localhost/SA_Ferrorama_TR4/api/index.php/saude
   Deve aparecer: `{"api":"ok","banco":"ok"}`
6. **Abra o site:** http://localhost/SA_Ferrorama_TR4/ e entre com `gestor@tr4.com` / `123456`.

> O site precisa ser aberto pelo **Apache** (endereço `http://localhost/...`).
> O Live Server e o "abrir arquivo direto" não rodam PHP.

## Estrutura

```
api/
├── index.php            porta de entrada: todo pedido passa por aqui
├── config.php           dados de conexão com o MySQL
├── .htaccess            bloqueia o acesso direto ao config.php
└── src/
    ├── funcoes.php      respostas JSON, erros, roteamento e validações
    ├── banco.php        conexão PDO e funções consultar() / executar()
    ├── autenticacao.php senha, sessão e permissões por cargo
    └── endpoints/       um arquivo por assunto
        ├── auth.php         login, cadastro, "quem sou eu", sair, saúde
        ├── usuarios.php     perfil e cargo de quem está logado
        ├── opcoes.php       listas dos <select>
        ├── rotas.php        rotas, horários e linhas
        ├── trens.php        trens e resumo do gráfico
        ├── cargas.php       cadastro, monitoramento e simulação
        ├── manutencoes.php  registro de manutenção
        ├── sensores.php     instalar / remover sensores
        ├── funcionarios.php lista e edição
        ├── alertas.php      lista e exclusão
        └── relatorios.php   números do resumo
```

### Como um endpoint funciona

Cada arquivo em `src/endpoints/` registra seus endereços com `rota()`:

```php
// GET /rotas/2
rota('GET', '/rotas/(\d+)', function (int $id) {
    exigirCargo();                                   // precisa estar logado
    $rota = consultarUm('SELECT * FROM rotas WHERE id = ?', [$id]);
    if (!$rota) {
        throw new ErroApi(404, 'Rota não encontrada.');
    }
    responder($rota);                                // devolve em JSON
});
```

Para criar um endpoint novo, crie um arquivo `.php` em `src/endpoints/`: o `index.php` carrega todos sozinho.

## Endpoints

Todos começam com `api/index.php`, por exemplo `api/index.php/rotas`.
O login fica na **sessão do PHP** (um cookie que o navegador manda sozinho).

| Método | Endereço | Quem pode | Usado em |
|---|---|---|---|
| GET | `/saude` | todos | testar se API e banco estão ok |
| POST | `/auth/login` | todos | login.html |
| POST | `/auth/cadastro` | todos | signin.html |
| GET | `/auth/eu` | logado | todas as páginas |
| POST | `/auth/sair` | todos | botão "Sair" |
| PUT | `/usuarios/eu/cargo` | logado | selecao-usuario.html |
| PUT | `/usuarios/eu` | logado | perfil-pessoal.html |
| GET | `/opcoes` | qualquer cargo | selects dos formulários |
| GET | `/rotas` · `/rotas/{id}` | qualquer cargo | gestao-rotas, gestao-geral |
| GET | `/trens` · `/trens/resumo` | qualquer cargo | gestao-geral, manutencao |
| GET | `/cargas?status=` | qualquer cargo | monitoramento, relatorios |
| POST | `/cargas` | gestor | cadastro-carga |
| POST | `/cargas/simular` | qualquer cargo | monitoramento (simulação) |
| GET · POST | `/manutencoes` | gestor, maquinista | manutencao, relatorios |
| GET · POST | `/sensores` | gestor | gestao/adicionar-sensor |
| PUT | `/sensores/{id}/remover` | gestor | remover-sensor |
| GET | `/funcionarios?busca=` · `/funcionarios/{id}` | gestor | funcionarios, perfil-funcionario |
| PUT | `/funcionarios/{id}` | gestor | perfil-funcionario |
| GET | `/alertas?busca=` | qualquer cargo | alertas |
| DELETE | `/alertas/{id}` | gestor | alertas |
| GET | `/relatorios/resumo` | gestor, maquinista | relatorios |

Erros sempre voltam assim: `{ "erro": "mensagem para o usuário", "campo": "id-do-campo" }`.
O site usa o `campo` para mostrar a mensagem embaixo do campo certo.

## Problemas comuns

| O que aparece | O que fazer |
|---|---|
| "A API não respondeu. Abra o site pelo Apache..." | O site foi aberto pelo Live Server ou direto do arquivo. Use `http://localhost/SA_Ferrorama_TR4/`. |
| "Não foi possível falar com a API" | O Apache está desligado no XAMPP. |
| "O banco de dados não está disponível" + `[2002]` | O MySQL está desligado no XAMPP. |
| "O banco de dados não está disponível" + `[1045]` | Usuário ou senha errados em `api/config.php`. |
| "O banco de dados não está disponível" + `[1049]` | O banco não foi criado. Importe `banco-de-dados/ferrorama_tr4.sql`. |
| Página em branco ou 404 | Confira se a pasta em `htdocs` se chama `SA_Ferrorama_TR4` e se o endereço está igual. |

## Segurança (para quando for de verdade)

- Em `config.php`, mude `MODO_DESENVOLVIMENTO` para `false`: assim os detalhes técnicos dos erros não aparecem para o usuário.
- Não envie a senha real do MySQL para o GitHub.
- As senhas dos usuários ficam com SHA-256 + salt, o mesmo cálculo do script do banco.
  Em produção, prefira `password_hash()` / `password_verify()` do PHP.
