<?php

require "conexao.php";

session_unset();
session_destroy();

responder(["ok" => true]);
