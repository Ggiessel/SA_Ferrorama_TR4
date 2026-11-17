<?php

// Mantido por compatibilidade: a exportação de leituras agora é feita por exportar.php?tipo=leituras.
$_GET["tipo"] = "leituras";
require "exportar.php";
