<?php

require "conexao.php";

session_destroy();

responder(["ok" => true]);
