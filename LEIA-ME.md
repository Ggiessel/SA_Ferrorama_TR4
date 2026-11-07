# Ferrorama TR4

Sistema web de gestão ferroviária: HTML, CSS (Bootstrap), JavaScript, PHP e MySQL.

## Como rodar (XAMPP)

1. Copie esta pasta para `C:\xampp\htdocs\sa_ferrorama_tr4`.
2. Ligue o **Apache** e o **MySQL** no XAMPP.
3. No phpMyAdmin, importe `banco-de-dados/ferrorama_tr4.sql`.
4. Abra `http://localhost:8080/sa_ferrorama_tr4/` (troque `8080` pela porta do seu Apache).

Se o MySQL tiver senha, coloque em `$senhaBanco` no `api/conexao.php`.
Não use o Live Server: ele não roda PHP.

| Conta de teste | Senha | Cargo |
|---|---|---|
| gestor@tr4.com | 123456 | Gestor |
| maquinista@tr4.com | 123456 | Maquinista |
| cliente@tr4.com | 123456 | Cliente |

## Pastas

| Pasta | O que tem |
|---|---|
| `paginas/` | As 19 telas (HTML) |
| `css/` | `ferrorama-bootstrap.css`, o tema de todas as telas |
| `js/` | `global.js` (funções comuns) + um script por página |
| `api/` | `conexao.php` + um arquivo PHP por assunto |
| `banco-de-dados/` | `ferrorama_tr4.sql` (tabelas e dados de exemplo) |
| `img/` | Imagens |
