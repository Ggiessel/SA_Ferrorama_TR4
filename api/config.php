<?php
/*
============================================================
  FERRORAMA TR4 — CONFIGURAÇÃO DA API
  Arquivo: api/config.php
============================================================

  Dados de conexão com o MySQL.
  Os valores abaixo são os padrões do XAMPP (usuário root, sem senha).
  Se o seu MySQL tiver senha, coloque em DB_SENHA.

  Cuidado: não envie uma senha de verdade para o GitHub.
*/

define('DB_HOST', 'localhost');
define('DB_PORTA', 3306);
define('DB_USUARIO', 'root');
define('DB_SENHA', '');
define('DB_NOME', 'ferrorama_tr4');

// Fuso horário usado nas datas (ex.: "hoje" no cadastro de carga)
date_default_timezone_set('America/Sao_Paulo');

// true = mostra o erro técnico na resposta (útil enquanto desenvolve).
// Coloque false quando o sistema for usado de verdade.
define('MODO_DESENVOLVIMENTO', true);
