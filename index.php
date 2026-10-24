<?php
// Raiz do domínio: se for o domínio próprio de um cliente, mostra o site dele;
// se for o domínio da plataforma, leva pro login (ou pro painel de quem já entrou).
require_once __DIR__ . '/config/db.php';
$host = preg_replace('/^www\./i', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));
if ($host !== '') {
    try {
        $st = $pdo->prepare("SELECT 1 FROM empresas WHERE dominio_personalizado = ? LIMIT 1");
        $st->execute([$host]);
        if ($st->fetch()) { require __DIR__ . '/site/index.php'; exit; }
    } catch (PDOException $e) { /* coluna ainda não existe: segue pro login */ }
}
header('Location: /login.php');
exit;
