<?php
/**
 * Sistema DFD - Listagem de DFDs
 */
require_once 'includes/config.php';
requireAuth();

$db = getDB();
$user = getLoggedUser();

// Parâmetros de busca e paginação
$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$porPagina = 15;
$offset = ($pagina - 1) * $porPagina;

// Query base
$where = [];
$params = [];

// Filtrar por centro de custo do usuário (se não for admin)
if (!isAdmin() && !empty($user['centro_custo_nome'])) {
    $where[] = "centro_custo = ?";
    $params[] = $user['centro_custo_nome'];
}

if (!empty($busca)) {
    $where[] = "(numero LIKE ? OR responsavel_nome LIKE ? OR objeto_resumido LIKE ? OR centro_custo LIKE ?)";
    $searchTerm = '%' . $busca . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Total de registros
$countSql = "SELECT COUNT(*) FROM dfds $whereClause";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRegistros = $countStmt->fetchColumn();
$totalPaginas = ceil($totalRegistros / $porPagina);

// Buscar registros
$sql = "SELECT d.*, u.nome as criado_por FROM dfds d LEFT JOIN usuarios u ON d.usuario_id = u.id $whereClause ORDER BY d.created_at DESC LIMIT $porPagina OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$dfds = $stmt->fetchAll();

// Mensagem flash
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php renderHead('DFDs'); ?>
</head>
<body>
    <?php renderNavbar('dfds'); ?>

    <div class="container">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-list-ul me-2"></i>Documentos de Formalização de Demanda</h4>
                <p class="text-muted mb-0">
                    <?= $totalRegistros ?> documento(s) cadastrado(s)
                    <?php if (!isAdmin() && !empty($user['centro_custo_nome'])): ?>
                        — <span class="badge bg-info"><?= sanitize($user['centro_custo_nome']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="novo.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>
                Novo DFD
            </a>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> fade-in" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $flash['message'] ?>
        </div>
        <?php endif; ?>

        <!-- Card de Listagem -->
        <div class="card fade-in">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-folder2-open me-2"></i>Lista de DFDs</h5>
                <!-- Busca -->
                <form method="GET" class="d-flex gap-2" style="width: 300px;">
                    <div class="input-group input-group-sm">
                        <input type="text" name="busca" class="form-control bg-white" 
                               placeholder="Buscar..." value="<?= sanitize($busca) ?>">
                        <button type="submit" class="btn btn-light">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dfds)): ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p class="mb-0">Nenhum DFD encontrado</p>
                    <?php if (!empty($busca)): ?>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm mt-2">Limpar busca</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Centro de Custo</th>
                                <th>Objeto Resumido</th>
                                <th>Responsável</th>
                                <th class="text-end">Valor Total</th>
                                <th>Data</th>
                                <th class="text-center" style="width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dfds as $dfd): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?= sanitize($dfd['numero']) ?></span>
                                </td>
                                <td><?= sanitize($dfd['centro_custo']) ?></td>
                                <td>
                                    <span title="<?= sanitize($dfd['objeto_resumido']) ?>">
                                        <?= sanitize(mb_strimwidth($dfd['objeto_resumido'], 0, 50, '...')) ?>
                                    </span>
                                </td>
                                <td><?= sanitize($dfd['responsavel_nome']) ?></td>
                                <td class="text-end fw-bold"><?= formatMoney($dfd['valor_total']) ?></td>
                                <td><?= formatDate($dfd['data_documento']) ?></td>
                                <td>
                                    <div class="action-buttons d-flex justify-content-center gap-1">
                                        <a href="visualizar.php?id=<?= $dfd['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar.php?id=<?= $dfd['id'] ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="gerar_pdf.php?id=<?= $dfd['id'] ?>" 
                                           class="btn btn-sm btn-outline-success" title="Gerar PDF" target="_blank">
                                            <i class="bi bi-file-pdf"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?= $dfd['id'] ?>, '<?= sanitize($dfd['numero']) ?>')"
                                                title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                <!-- Paginação -->
                <div class="d-flex justify-content-center p-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($pagina > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina - 1 ?>&busca=<?= urlencode($busca) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $pagina - 2); $i <= min($totalPaginas, $pagina + 2); $i++): ?>
                            <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $totalPaginas): ?>
                            <li class="page-item">
                                <a class="page-link" href="?pagina=<?= $pagina + 1 ?>&busca=<?= urlencode($busca) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php renderFooter(); ?>
    </div>

    <?php renderScripts(); ?>
</body>
</html>
