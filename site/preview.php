<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/site_render.php';

// Autenticação: admin vê qualquer empresa, a empresa só vê a própria
if (empty($_SESSION['usuario_id'])) { http_response_code(403); echo 'Acesso negado.'; exit; }
$empresaIdSolicitada = (int)($_GET['empresa_id'] ?? 0);
if (($_SESSION['role'] ?? '') !== 'admin' && (int)($_SESSION['empresa_id'] ?? 0) !== $empresaIdSolicitada) {
    http_response_code(403); echo 'Acesso negado.'; exit;
}
session_write_close(); // a pré-visualização não precisa travar a sessão (o Estúdio IA faz chamadas longas em paralelo)

$modo = ($_GET['modo'] ?? 'template') === 'ia' ? 'ia' : 'template';

$stmt = $pdo->prepare(SITE_SQL_BASE . " WHERE e.id = ? LIMIT 1");
$stmt->execute([$empresaIdSolicitada]);
$empresa = $stmt->fetch();
if (!$empresa) { echo 'Empresa não encontrada.'; exit; }

$dados = site_carregar_dados($empresa);
$estado = site_estado_vazio();

if ($modo === 'ia') {
    if (empty($empresa['html_gerado'])) {
        echo '<p style="font-family:sans-serif;padding:40px;text-align:center;color:#666;">Nenhum site gerado por IA ainda. Abra o Estúdio IA, descreva o negócio e clique em "Gerar meu site".</p>';
        exit;
    }
    echo site_render_ia($empresa, $dados, $estado, true);
} else {
    echo site_render_template($empresa, $dados, $estado, true);
}
