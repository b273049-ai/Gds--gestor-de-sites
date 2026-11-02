<?php
// Conta uma visualização da ficha do imóvel (chamado pelo site com sendBeacon)
require_once __DIR__ . '/../config/db.php';
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) { try { $pdo->prepare("UPDATE imoveis SET visualizacoes = visualizacoes + 1 WHERE id=? AND ativo=true")->execute([$id]); } catch (PDOException $e) {} }
http_response_code(204);
