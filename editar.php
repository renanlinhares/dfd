<?php
/**
 * Sistema DFD - Editar DFD
 */
require_once 'includes/config.php';
requireAuth();

$db = getDB();
$user = getLoggedUser();
$erro = '';

// Verificar ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Buscar DFD
$stmt = $db->prepare("SELECT * FROM dfds WHERE id = ?");
$stmt->execute([$id]);
$dfd = $stmt->fetch();

if (!$dfd) {
    setFlash('danger', 'DFD não encontrado.');
    header('Location: index.php');
    exit;
}

// Buscar itens do DFD
$stmtItens = $db->prepare("SELECT * FROM dfd_itens WHERE dfd_id = ? ORDER BY item_numero");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll();

// Buscar centros de custo para autocomplete
$centros = $db->query("SELECT nome FROM centros_custo WHERE ativo = 1 ORDER BY nome")->fetchAll(PDO::FETCH_COLUMN);

// Decodificar JSON
$modalidadesSelecionadas = json_decode($dfd['modalidade_licitacao'] ?? '[]', true) ?: [];
$procedimentosSelecionados = json_decode($dfd['procedimento_auxiliar'] ?? '[]', true) ?: [];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Coletar modalidades selecionadas
        $modalidades = isset($_POST['modalidade']) ? json_encode($_POST['modalidade']) : '[]';
        $procedimentos = isset($_POST['procedimento']) ? json_encode($_POST['procedimento']) : '[]';
        
        // Calcular valor total dos itens
        $valorTotal = 0;
        if (isset($_POST['itens']) && is_array($_POST['itens'])) {
            foreach ($_POST['itens'] as $item) {
                $qtd = floatval($item['quantidade'] ?? 0);
                $vlr = floatval($item['valor_unitario'] ?? 0);
                $valorTotal += $qtd * $vlr;
            }
        }
        
        // Atualizar DFD
        $sql = "UPDATE dfds SET
            centro_custo = :centro_custo,
            data_documento = :data_documento,
            local_entrega = :local_entrega,
            fornecedor = :fornecedor,
            responsavel_nome = :responsavel_nome,
            responsavel_matricula = :responsavel_matricula,
            responsavel_email = :responsavel_email,
            objeto_resumido = :objeto_resumido,
            justificativa = :justificativa,
            prazo_entrega = :prazo_entrega,
            forma_entrega = :forma_entrega,
            previsao_consumo = :previsao_consumo,
            gestor_nome = :gestor_nome,
            gestor_matricula = :gestor_matricula,
            fiscal_nome = :fiscal_nome,
            fiscal_matricula = :fiscal_matricula,
            mapa_precos_qtd = :mapa_precos_qtd,
            dotacao_numero = :dotacao_numero,
            dotacao_funcional = :dotacao_funcional,
            dotacao_orgao = :dotacao_orgao,
            dotacao_acao = :dotacao_acao,
            dotacao_unidade = :dotacao_unidade,
            dotacao_valor_reservado = :dotacao_valor_reservado,
            prioridade = :prioridade,
            prioridade_motivo = :prioridade_motivo,
            razao_escolha = :razao_escolha,
            razao_justificativa = :razao_justificativa,
            vinculado_outro_dfd = :vinculado_outro_dfd,
            vinculado_dfd_numero = :vinculado_dfd_numero,
            modalidade_licitacao = :modalidade_licitacao,
            procedimento_auxiliar = :procedimento_auxiliar,
            posicionamento_conclusivo = :posicionamento_conclusivo,
            valor_total = :valor_total,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':centro_custo' => $_POST['centro_custo'] ?? '',
            ':data_documento' => $_POST['data_documento'] ?? date('Y-m-d'),
            ':local_entrega' => $_POST['local_entrega'] ?? '',
            ':fornecedor' => $_POST['fornecedor'] ?? '',
            ':responsavel_nome' => $_POST['responsavel_nome'] ?? '',
            ':responsavel_matricula' => $_POST['responsavel_matricula'] ?? '',
            ':responsavel_email' => $_POST['responsavel_email'] ?? '',
            ':objeto_resumido' => $_POST['objeto_resumido'] ?? '',
            ':justificativa' => $_POST['justificativa'] ?? '',
            ':prazo_entrega' => $_POST['prazo_entrega'] ?? '',
            ':forma_entrega' => $_POST['forma_entrega'] ?? '',
            ':previsao_consumo' => $_POST['previsao_consumo'] ?? '',
            ':gestor_nome' => $_POST['gestor_nome'] ?? '',
            ':gestor_matricula' => $_POST['gestor_matricula'] ?? '',
            ':fiscal_nome' => $_POST['fiscal_nome'] ?? '',
            ':fiscal_matricula' => $_POST['fiscal_matricula'] ?? '',
            ':mapa_precos_qtd' => intval($_POST['mapa_precos_qtd'] ?? 0),
            ':dotacao_numero' => $_POST['dotacao_numero'] ?? '',
            ':dotacao_funcional' => $_POST['dotacao_funcional'] ?? '',
            ':dotacao_orgao' => $_POST['dotacao_orgao'] ?? '',
            ':dotacao_acao' => $_POST['dotacao_acao'] ?? '',
            ':dotacao_unidade' => $_POST['dotacao_unidade'] ?? '',
            ':dotacao_valor_reservado' => floatval($_POST['dotacao_valor_reservado'] ?? 0),
            ':prioridade' => $_POST['prioridade'] ?? 'normal',
            ':prioridade_motivo' => $_POST['prioridade_motivo'] ?? '',
            ':razao_escolha' => $_POST['razao_escolha'] ?? '',
            ':razao_justificativa' => $_POST['razao_justificativa'] ?? '',
            ':vinculado_outro_dfd' => $_POST['vinculado_outro_dfd'] ?? 'nao',
            ':vinculado_dfd_numero' => $_POST['vinculado_dfd_numero'] ?? '',
            ':modalidade_licitacao' => $modalidades,
            ':procedimento_auxiliar' => $procedimentos,
            ':posicionamento_conclusivo' => $_POST['posicionamento_conclusivo'] ?? '',
            ':valor_total' => $valorTotal,
            ':id' => $id
        ]);
        
        // Remover itens antigos
        $db->prepare("DELETE FROM dfd_itens WHERE dfd_id = ?")->execute([$id]);
        
        // Inserir novos itens
        if (isset($_POST['itens']) && is_array($_POST['itens'])) {
            $itemStmt = $db->prepare("INSERT INTO dfd_itens (
                dfd_id, item_numero, especificacao, unidade, quantidade, 
                marca_modelo, valor_unitario, valor_total
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $itemNum = 1;
            foreach ($_POST['itens'] as $item) {
                if (empty(trim($item['especificacao'] ?? ''))) continue;
                
                $qtd = floatval($item['quantidade'] ?? 0);
                $vlrUnit = floatval($item['valor_unitario'] ?? 0);
                $vlrTotal = $qtd * $vlrUnit;
                
                $itemStmt->execute([
                    $id,
                    $itemNum,
                    $item['especificacao'],
                    $item['unidade'] ?? '',
                    $qtd,
                    $item['marca_modelo'] ?? '',
                    $vlrUnit,
                    $vlrTotal
                ]);
                $itemNum++;
            }
        }
        
        $db->commit();
        
        setFlash('success', "DFD {$dfd['numero']} atualizado com sucesso!");
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $erro = 'Erro ao atualizar DFD: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php renderHead('Editar DFD ' . $dfd['numero']); ?>
</head>
<body>
    <?php renderNavbar('dfds'); ?>

    <div class="container">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">
                    <i class="bi bi-pencil me-2"></i>Editar DFD 
                    <span class="badge bg-primary"><?= sanitize($dfd['numero']) ?></span>
                </h4>
                <p class="text-muted mb-0">Altere os campos necessários</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
                Voltar
            </a>
        </div>

        <?php if ($erro): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i><?= sanitize($erro) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="dfd-form">
            <div class="card fade-in mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle me-2"></i>Informações Gerais</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Centro de Custo</label>
                            <input type="text" name="centro_custo" class="form-control" 
                                   list="centros-custo" required value="<?= sanitize($dfd['centro_custo']) ?>">
                            <datalist id="centros-custo">
                                <?php foreach ($centros as $centro): ?>
                                <option value="<?= sanitize($centro) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Data do Documento</label>
                            <input type="date" name="data_documento" class="form-control" 
                                   value="<?= $dfd['data_documento'] ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fornecedor/Contratado</label>
                            <input type="text" name="fornecedor" class="form-control" 
                                   value="<?= sanitize($dfd['fornecedor']) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Local de Entrega</label>
                            <input type="text" name="local_entrega" class="form-control" 
                                   value="<?= sanitize($dfd['local_entrega']) ?>">
                        </div>
                    </div>

                    <!-- Responsável pela Demanda -->
                    <div class="section-header">
                        <h6><i class="bi bi-person me-2"></i>Responsável pela Demanda/Requisitante</h6>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label required">Nome</label>
                            <input type="text" name="responsavel_nome" class="form-control" required
                                   value="<?= sanitize($dfd['responsavel_nome']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nº Matrícula</label>
                            <input type="text" name="responsavel_matricula" class="form-control"
                                   value="<?= sanitize($dfd['responsavel_matricula']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="responsavel_email" class="form-control"
                                   value="<?= sanitize($dfd['responsavel_email']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seções do DFD -->
            <div class="card fade-in mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-card-text me-2"></i>Detalhes da Demanda</h5>
                </div>
                <div class="card-body">
                    <!-- 1. Objeto Resumido -->
                    <div class="section-header">
                        <h6><span class="section-number">1</span> Objeto Resumido</h6>
                    </div>
                    <textarea name="objeto_resumido" class="form-control" rows="3" required><?= sanitize($dfd['objeto_resumido']) ?></textarea>

                    <!-- 2. Justificativa -->
                    <div class="section-header">
                        <h6><span class="section-number">2</span> Justificativa da Necessidade da Contratação/Motivação</h6>
                    </div>
                    <textarea name="justificativa" class="form-control" rows="4" required><?= sanitize($dfd['justificativa']) ?></textarea>

                    <!-- 3. Prazo de Entrega -->
                    <div class="section-header">
                        <h6><span class="section-number">3</span> Prazo de Entrega, Local e Horário</h6>
                    </div>
                    <textarea name="prazo_entrega" class="form-control" rows="2"><?= sanitize($dfd['prazo_entrega']) ?></textarea>

                    <!-- 4. Forma de Entrega -->
                    <div class="section-header">
                        <h6><span class="section-number">4</span> Forma de Entrega</h6>
                    </div>
                    <div class="row g-3">
                        <?php foreach (getFormasEntrega() as $key => $label): ?>
                        <div class="col-auto">
                            <div class="form-check">
                                <input type="radio" name="forma_entrega" value="<?= $key ?>" 
                                       class="form-check-input" id="forma_<?= $key ?>"
                                       <?= $dfd['forma_entrega'] === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="forma_<?= $key ?>"><?= $label ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 5. Previsão de Consumo -->
                    <div class="section-header">
                        <h6><span class="section-number">5</span> Previsão de Consumo</h6>
                    </div>
                    <div class="row g-3">
                        <?php foreach (getPrevisaoConsumo() as $key => $label): ?>
                        <div class="col-auto">
                            <div class="form-check">
                                <input type="radio" name="previsao_consumo" value="<?= $key ?>" 
                                       class="form-check-input" id="prev_<?= $key ?>"
                                       <?= $dfd['previsao_consumo'] === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="prev_<?= $key ?>"><?= $label ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 6. Gestão e Fiscalização -->
                    <div class="section-header">
                        <h6><span class="section-number">6</span> Gestão e Fiscalização Contratual</h6>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-person-badge text-primary"></i>
                                    <strong>Gestor</strong>
                                </div>
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="text" name="gestor_nome" class="form-control form-control-sm" 
                                               value="<?= sanitize($dfd['gestor_nome']) ?>">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="gestor_matricula" class="form-control form-control-sm" 
                                               value="<?= sanitize($dfd['gestor_matricula']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-clipboard-check text-success"></i>
                                    <strong>Fiscal</strong>
                                </div>
                                <div class="row g-2">
                                    <div class="col-8">
                                        <input type="text" name="fiscal_nome" class="form-control form-control-sm" 
                                               value="<?= sanitize($dfd['fiscal_nome']) ?>">
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="fiscal_matricula" class="form-control form-control-sm" 
                                               value="<?= sanitize($dfd['fiscal_matricula']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. Detalhamento e Itens -->
            <div class="card fade-in mb-4">
                <div class="card-header">
                    <h5><span class="section-number me-2">7</span>Detalhamento do Objeto e Estimativa de Preço</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table items-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Item</th>
                                    <th>Especificação</th>
                                    <th style="width: 80px;">Und.</th>
                                    <th style="width: 100px;">Qtd.</th>
                                    <th style="width: 150px;">Marca/Modelo</th>
                                    <th style="width: 130px;">V. Unitário</th>
                                    <th style="width: 130px;">V. Total</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                <?php if (empty($itens)): ?>
                                <tr>
                                    <td class="text-center fw-bold">1</td>
                                    <td><input type="text" name="itens[0][especificacao]" class="form-control item-especificacao"></td>
                                    <td><input type="text" name="itens[0][unidade]" class="form-control item-unidade"></td>
                                    <td><input type="number" name="itens[0][quantidade]" class="form-control item-quantidade" step="0.0001" min="0"></td>
                                    <td><input type="text" name="itens[0][marca_modelo]" class="form-control item-marca"></td>
                                    <td><input type="text" name="itens[0][valor_unitario]" class="form-control item-valor-unitario money-input"></td>
                                    <td><span class="item-valor-total fw-bold text-primary">R$ 0,00</span></td>
                                    <td class="text-center"><button type="button" class="btn-remove-item"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($itens as $idx => $item): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $idx + 1 ?></td>
                                    <td><input type="text" name="itens[<?= $idx ?>][especificacao]" class="form-control item-especificacao" value="<?= sanitize($item['especificacao']) ?>"></td>
                                    <td><input type="text" name="itens[<?= $idx ?>][unidade]" class="form-control item-unidade" value="<?= sanitize($item['unidade']) ?>"></td>
                                    <td><input type="number" name="itens[<?= $idx ?>][quantidade]" class="form-control item-quantidade" step="0.0001" min="0" value="<?= $item['quantidade'] ?>"></td>
                                    <td><input type="text" name="itens[<?= $idx ?>][marca_modelo]" class="form-control item-marca" value="<?= sanitize($item['marca_modelo']) ?>"></td>
                                    <td><input type="text" name="itens[<?= $idx ?>][valor_unitario]" class="form-control item-valor-unitario money-input" value="<?= formatMoney($item['valor_unitario']) ?>"></td>
                                    <td><span class="item-valor-total fw-bold text-primary"><?= formatMoney($item['valor_total']) ?></span></td>
                                    <td class="text-center"><button type="button" class="btn-remove-item"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" id="btn-add-item" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Adicionar Item
                        </button>
                        <div class="total-display">
                            <div class="label">Valor Total</div>
                            <div class="value" id="valor-total"><?= formatMoney($dfd['valor_total']) ?></div>
                            <input type="hidden" name="valor_total" id="valor-total-hidden" value="<?= $dfd['valor_total'] ?>">
                        </div>
                    </div>

                    <!-- 7.1 Mapa de Preços -->
                    <div class="section-header">
                        <h6>7.1 Do Mapa de Preços</h6>
                    </div>
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <span>O mapa de preços foi formado por ao menos</span>
                        </div>
                        <div class="col-auto">
                            <input type="number" name="mapa_precos_qtd" class="form-control" 
                                   style="width: 80px;" min="0" value="<?= $dfd['mapa_precos_qtd'] ?>">
                        </div>
                        <div class="col">
                            <span>pesquisas de preços realizadas na forma estabelecida no Decreto Municipal.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 8. Dotação Orçamentária -->
            <div class="card fade-in mb-4">
                <div class="card-header">
                    <h5><span class="section-number me-2">8</span>Dotação Orçamentária</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Número da Dotação</label>
                            <input type="text" name="dotacao_numero" class="form-control" value="<?= sanitize($dfd['dotacao_numero']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número da Funcional</label>
                            <input type="text" name="dotacao_funcional" class="form-control" value="<?= sanitize($dfd['dotacao_funcional']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número do Órgão</label>
                            <input type="text" name="dotacao_orgao" class="form-control" value="<?= sanitize($dfd['dotacao_orgao']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número da Ação</label>
                            <input type="text" name="dotacao_acao" class="form-control" value="<?= sanitize($dfd['dotacao_acao']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Número da Unidade</label>
                            <input type="text" name="dotacao_unidade" class="form-control" value="<?= sanitize($dfd['dotacao_unidade']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor a ser Reservado</label>
                            <input type="text" name="dotacao_valor_reservado" class="form-control money-input" 
                                   value="<?= formatMoney($dfd['dotacao_valor_reservado']) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 9 a 13 - Finalização -->
            <div class="card fade-in mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-check-circle me-2"></i>Finalização</h5>
                </div>
                <div class="card-body">
                    <!-- 9. Grau de Prioridade -->
                    <div class="section-header">
                        <h6><span class="section-number">9</span> Grau de Prioridade da Compra</h6>
                    </div>
                    <div class="row g-3 align-items-start">
                        <?php foreach (getPrioridades() as $key => $label): ?>
                        <div class="col-auto">
                            <div class="form-check">
                                <input type="radio" name="prioridade" value="<?= $key ?>" 
                                       class="form-check-input" id="prio_<?= $key ?>"
                                       <?= $dfd['prioridade'] === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="prio_<?= $key ?>"><?= $label ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="motivo-urgencia-container" class="mt-2" style="display: <?= $dfd['prioridade'] === 'urgente' ? 'block' : 'none' ?>;">
                        <label class="form-label">Motivo da Urgência</label>
                        <textarea name="prioridade_motivo" class="form-control" rows="2"><?= sanitize($dfd['prioridade_motivo']) ?></textarea>
                    </div>

                    <!-- 10. Razão de Escolha (Dispensas) -->
                    <div class="section-header">
                        <h6><span class="section-number">10</span> Exclusivamente para Dispensas de Licitação - Razão de Escolha do Contratado</h6>
                    </div>
                    <div class="row g-2">
                        <?php foreach (getRazoesEscolha() as $key => $label): ?>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="radio" name="razao_escolha" value="<?= $key ?>" 
                                       class="form-check-input" id="razao_<?= $key ?>"
                                       <?= $dfd['razao_escolha'] === $key ? 'checked' : '' ?>>
                                <label class="form-check-label" for="razao_<?= $key ?>"><?= $label ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="razao-justificativa-container" class="mt-2" style="display: <?= in_array($dfd['razao_escolha'], ['menores_custos', 'maior_ciclo', 'unico_fornecedor']) ? 'block' : 'none' ?>;">
                        <label class="form-label">Justificativa</label>
                        <textarea name="razao_justificativa" class="form-control" rows="2"><?= sanitize($dfd['razao_justificativa']) ?></textarea>
                    </div>

                    <!-- 11. Vinculação -->
                    <div class="section-header">
                        <h6><span class="section-number">11</span> Vinculado ou Dependente de Outro DFD</h6>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <div class="form-check form-check-inline">
                                <input type="radio" name="vinculado_outro_dfd" value="sim" 
                                       class="form-check-input" id="vinc_sim"
                                       <?= $dfd['vinculado_outro_dfd'] === 'sim' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vinc_sim">Sim</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="radio" name="vinculado_outro_dfd" value="nao" 
                                       class="form-check-input" id="vinc_nao"
                                       <?= $dfd['vinculado_outro_dfd'] !== 'sim' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="vinc_nao">Não</label>
                            </div>
                        </div>
                    </div>
                    <div id="vinculado-dfd-container" class="mt-2" style="display: <?= $dfd['vinculado_outro_dfd'] === 'sim' ? 'block' : 'none' ?>;">
                        <label class="form-label">Número do DFD Vinculado</label>
                        <input type="text" name="vinculado_dfd_numero" class="form-control" style="max-width: 200px;"
                               value="<?= sanitize($dfd['vinculado_dfd_numero']) ?>">
                    </div>

                    <!-- 12. Posicionamento Conclusivo -->
                    <div class="section-header">
                        <h6><span class="section-number">12</span> Posicionamento Conclusivo</h6>
                    </div>
                    <textarea name="posicionamento_conclusivo" class="form-control" rows="3"><?= sanitize($dfd['posicionamento_conclusivo']) ?></textarea>

                    <!-- 13. Modalidade -->
                    <div class="section-header">
                        <h6><span class="section-number">13</span> Modalidade de Licitação/Contratação Direta Pretendida</h6>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Modalidade</label>
                            <div class="row g-2">
                                <?php foreach (getModalidades() as $key => $label): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input type="checkbox" name="modalidade[]" value="<?= $key ?>" 
                                               class="form-check-input" id="mod_<?= $key ?>"
                                               <?= in_array($key, $modalidadesSelecionadas) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="mod_<?= $key ?>"><?= $label ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Procedimento Auxiliar</label>
                            <?php foreach (getProcedimentosAuxiliares() as $key => $label): ?>
                            <div class="form-check">
                                <input type="checkbox" name="procedimento[]" value="<?= $key ?>" 
                                       class="form-check-input" id="proc_<?= $key ?>"
                                       <?= in_array($key, $procedimentosSelecionados) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="proc_<?= $key ?>"><?= $label ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botões -->
            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>Salvar Alterações
                </button>
            </div>
        </form>

        <?php renderFooter(); ?>
    </div>

    <?php renderScripts(); ?>
</body>
</html>
