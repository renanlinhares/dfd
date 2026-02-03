<?php
/**
 * Sistema DFD - Excluir DFD
 */
require_once 'includes/config.php';
requireAuth();

$db = getDB();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    setFlash('danger', 'DFD não encontrado.');
    header('Location: index.php');
    exit;
}

// Buscar DFD para confirmar existência e obter número
$stmt = $db->prepare("SELECT numero FROM dfds WHERE id = ?");
$stmt->execute([$id]);
$dfd = $stmt->fetch();

if (!$dfd) {
    setFlash('danger', 'DFD não encontrado.');
    header('Location: index.php');
    exit;
}

try {
    $db->beginTransaction();
    
    // Excluir itens primeiro
    $stmtItens = $db->prepare("DELETE FROM dfd_itens WHERE dfd_id = ?");
    $stmtItens->execute([$id]);
    
    // Excluir DFD
    $stmtDfd = $db->prepare("DELETE FROM dfds WHERE id = ?");
    $stmtDfd->execute([$id]);
    
    $db->commit();
    
    setFlash('success', "DFD {$dfd['numero']} excluído com sucesso.");
} catch (Exception $e) {
    $db->rollBack();
    setFlash('danger', 'Erro ao excluir DFD: ' . $e->getMessage());
}

header('Location: index.php');
exit;
