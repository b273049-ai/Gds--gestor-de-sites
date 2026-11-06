<?php
require_once __DIR__ . "/../includes/auth.php";
exigir_login("empresa");
header("Location: /empresa/meu_site.php");
exit;
