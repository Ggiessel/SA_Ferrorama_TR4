<?php

require "conexao.php";

session_unset();

$cookie = session_get_cookie_params();
setcookie(session_name(), "", time() - 3600, $cookie["path"], $cookie["domain"], $cookie["secure"], $cookie["httponly"]);

session_destroy();

responder(["ok" => true]);
