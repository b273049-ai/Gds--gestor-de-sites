<?php
// Raiz do domínio: se for o domínio próprio de um cliente, mostra o site dele;
// se for o domínio da plataforma, leva pro login (ou pro painel de quem já entrou).
require_once __DIR__ . '/config/db.php';
$host = preg_replace('/^www\./i', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]));

// Verificação de domínio próprio: responde quem é a empresa deste domínio (usado pelo painel)
if (isset($_GET['gds_verificar'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $id = null;
    try {
        $st = $pdo->prepare("SELECT id FROM empresas WHERE dominio_personalizado = ? LIMIT 1");
        $st->execute([$host]);
        $id = ($r = $st->fetch()) ? (int)$r['id'] : null;
    } catch (PDOException $e) { }
    echo json_encode(['gds' => 'ok', 'empresa' => $id, 'host' => $host]);
    exit;
}
if ($host !== '') {
    try {
        $st = $pdo->prepare("SELECT 1 FROM empresas WHERE dominio_personalizado = ? LIMIT 1");
        $st->execute([$host]);
        if ($st->fetch()) { require __DIR__ . '/site/index.php'; exit; }
    } catch (PDOException $e) { /* coluna ainda não existe: segue pro login */ }
}
header('Location: /login.php');
exit;
