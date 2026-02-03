<?php
/**
 * Sistema DFD - Visualizar DFD
 */
require_once 'includes/config.php';
requireAuth();

$db = getDB();

// Verificar ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    setFlash('danger', 'DFD não encontrado.');
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

// Buscar itens
$stmtItens = $db->prepare("SELECT * FROM dfd_itens WHERE dfd_id = ? ORDER BY item_numero");
$stmtItens->execute([$id]);
$itens = $stmtItens->fetchAll();

// Decodificar JSON
$modalidades = json_decode($dfd['modalidade_licitacao'] ?? '[]', true) ?: [];
$procedimentos = json_decode($dfd['procedimento_auxiliar'] ?? '[]', true) ?: [];

// Labels
$formasEntrega = getFormasEntrega();
$previsoes = getPrevisaoConsumo();
$prioridades = getPrioridades();
$razoes = getRazoesEscolha();
$modalidadesLabels = getModalidades();
$procedimentosLabels = getProcedimentosAuxiliares();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php renderHead('DFD ' . $dfd['numero']); ?>
</head>
<body>
    <?php renderNavbar('dfds'); ?>

    <div class="container">
        <!-- Cabeçalho -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">
                    <i class="bi bi-eye me-2"></i>DFD 
                    <span class="badge bg-primary fs-6"><?= sanitize($dfd['numero']) ?></span>
                </h4>
                <p class="text-muted mb-0">Visualização do Documento de Formalização de Demanda</p>
            </div>
            <div class="d-flex gap-2">
                <a href="editar.php?id=<?= $dfd['id'] ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
                <a href="gerar_pdf.php?id=<?= $dfd['id'] ?>" class="btn btn-outline-success" target="_blank">
                    <i class="bi bi-file-pdf me-1"></i>Gerar PDF
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>

        <!-- Informações Gerais -->
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5><i class="bi bi-info-circle me-2"></i>Informações Gerais</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="info-card">
                            <small class="text-muted d-block">Número do DFD</small>
                            <strong class="text-primary fs-5"><?= sanitize($dfd['numero']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-card">
                            <small class="text-muted d-block">Data do Documento</small>
                            <strong><?= formatDate($dfd['data_documento']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-card">
                            <small class="text-muted d-block">Centro de Custo</small>
                            <strong><?= sanitize($dfd['centro_custo']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-card">
                            <small class="text-muted d-block">Valor Total</small>
                            <strong class="text-success fs-5"><?= formatMoney($dfd['valor_total']) ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (!empty($dfd['fornecedor'])): ?>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="info-card">
                            <small class="text-muted d-block">Fornecedor/Contratado</small>
                            <strong><?= sanitize($dfd['fornecedor']) ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($dfd['local_entrega'])): ?>
                    <div class="col-md-6">
                        <div class="info-card">
                            <small class="text-muted d-block">Local de Entrega</small>
                            <strong><?= sanitize($dfd['local_entrega']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!empty($dfd['local_entrega'])): ?>
                <div class="row g-3 mt-1">
                    <div class="col-md-12">
                        <div class="info-card">
                            <small class="text-muted d-block">Local de Entrega</small>
                            <strong><?= sanitize($dfd['local_entrega']) ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Responsável -->
                <div class="section-header">
                    <h6><i class="bi bi-person me-2"></i>Responsável pela Demanda/Requisitante</h6>
                </div>
                <div class="row g-3">
                    <div class="col-md-5">
                        <div class="info-card">
                            <small class="text-muted d-block">Nome</small>
                            <strong><?= sanitize($dfd['responsavel_nome']) ?></strong>
                        </div>
                    </div>
                    <?php if (!empty($dfd['responsavel_matricula'])): ?>
                    <div class="col-md-3">
                        <div class="info-card">
                            <small class="text-muted d-block">Nº Matrícula</small>
                            <strong><?= sanitize($dfd['responsavel_matricula']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['responsavel_email'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">E-mail</small>
                            <strong><?= sanitize($dfd['responsavel_email']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detalhes da Demanda -->
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5><i class="bi bi-card-text me-2"></i>Detalhes da Demanda</h5>
            </div>
            <div class="card-body">
                <!-- 1. Objeto Resumido -->
                <div class="section-header">
                    <h6><span class="section-number">1</span> Objeto Resumido</h6>
                </div>
                <p class="mb-3"><?= nl2br(sanitize($dfd['objeto_resumido'])) ?></p>

                <!-- 2. Justificativa -->
                <div class="section-header">
                    <h6><span class="section-number">2</span> Justificativa da Necessidade da Contratação/Motivação</h6>
                </div>
                <p class="mb-3"><?= nl2br(sanitize($dfd['justificativa'])) ?></p>

                <!-- 3. Prazo de Entrega -->
                <?php if (!empty($dfd['prazo_entrega'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">3</span> Prazo de Entrega, Local e Horário</h6>
                </div>
                <p class="mb-3"><?= nl2br(sanitize($dfd['prazo_entrega'])) ?></p>
                <?php endif; ?>

                <!-- 4. Forma de Entrega -->
                <?php if (!empty($dfd['forma_entrega'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">4</span> Forma de Entrega</h6>
                </div>
                <p class="mb-3">
                    <span class="badge bg-info"><?= sanitize($formasEntrega[$dfd['forma_entrega']] ?? $dfd['forma_entrega']) ?></span>
                </p>
                <?php endif; ?>

                <!-- 5. Previsão de Consumo -->
                <?php if (!empty($dfd['previsao_consumo'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">5</span> Previsão de Consumo</h6>
                </div>
                <p class="mb-3">
                    <span class="badge bg-info"><?= sanitize($previsoes[$dfd['previsao_consumo']] ?? $dfd['previsao_consumo']) ?></span>
                </p>
                <?php endif; ?>

                <!-- 6. Gestão e Fiscalização -->
                <?php if (!empty($dfd['gestor_nome']) || !empty($dfd['fiscal_nome'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">6</span> Gestão e Fiscalização Contratual</h6>
                </div>
                <div class="row g-3 mb-3">
                    <?php if (!empty($dfd['gestor_nome'])): ?>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-person-badge text-primary"></i>
                                <strong>Gestor</strong>
                            </div>
                            <p class="mb-0">
                                <?= sanitize($dfd['gestor_nome']) ?>
                                <?php if (!empty($dfd['gestor_matricula'])): ?>
                                    <small class="text-muted">(Mat. <?= sanitize($dfd['gestor_matricula']) ?>)</small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['fiscal_nome'])): ?>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="bi bi-clipboard-check text-success"></i>
                                <strong>Fiscal</strong>
                            </div>
                            <p class="mb-0">
                                <?= sanitize($dfd['fiscal_nome']) ?>
                                <?php if (!empty($dfd['fiscal_matricula'])): ?>
                                    <small class="text-muted">(Mat. <?= sanitize($dfd['fiscal_matricula']) ?>)</small>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 7. Itens e Preços -->
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5><span class="section-number me-2">7</span>Detalhamento do Objeto e Estimativa de Preço</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($itens)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Item</th>
                                <th>Especificação</th>
                                <th style="width: 80px;">Und.</th>
                                <th class="text-end" style="width: 90px;">Qtd.</th>
                                <th style="width: 150px;">Marca/Modelo</th>
                                <th class="text-end" style="width: 130px;">V. Unitário</th>
                                <th class="text-end" style="width: 130px;">V. Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= intval($item['item_numero']) ?></td>
                                <td><?= sanitize($item['especificacao']) ?></td>
                                <td><?= sanitize($item['unidade']) ?></td>
                                <td class="text-end"><?= number_format($item['quantidade'], $item['quantidade'] == intval($item['quantidade']) ? 0 : 4, ',', '.') ?></td>
                                <td><?= sanitize($item['marca_modelo']) ?></td>
                                <td class="text-end"><?= formatMoney($item['valor_unitario']) ?></td>
                                <td class="text-end fw-bold"><?= formatMoney($item['valor_total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <td colspan="6" class="text-end fw-bold">TOTAL GERAL:</td>
                                <td class="text-end fw-bold fs-5"><?= formatMoney($dfd['valor_total']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">Nenhum item cadastrado.</p>
                <?php endif; ?>

                <!-- 7.1 Mapa de Preços -->
                <?php if ($dfd['mapa_precos_qtd'] > 0): ?>
                <div class="section-header">
                    <h6>7.1 Do Mapa de Preços</h6>
                </div>
                <p class="mb-0">O mapa de preços foi formado por ao menos <strong><?= intval($dfd['mapa_precos_qtd']) ?></strong> pesquisas de preços realizadas na forma estabelecida no Decreto Municipal.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 8. Dotação Orçamentária -->
        <?php if (!empty($dfd['dotacao_numero']) || !empty($dfd['dotacao_funcional']) || !empty($dfd['dotacao_orgao'])): ?>
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5><span class="section-number me-2">8</span>Dotação Orçamentária</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if (!empty($dfd['dotacao_numero'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Número da Dotação</small>
                            <strong><?= sanitize($dfd['dotacao_numero']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['dotacao_funcional'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Número da Funcional</small>
                            <strong><?= sanitize($dfd['dotacao_funcional']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['dotacao_orgao'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Número do Órgão</small>
                            <strong><?= sanitize($dfd['dotacao_orgao']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['dotacao_acao'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Número da Ação</small>
                            <strong><?= sanitize($dfd['dotacao_acao']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dfd['dotacao_unidade'])): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Número da Unidade</small>
                            <strong><?= sanitize($dfd['dotacao_unidade']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dfd['dotacao_valor_reservado'] > 0): ?>
                    <div class="col-md-4">
                        <div class="info-card">
                            <small class="text-muted d-block">Valor a ser Reservado</small>
                            <strong class="text-success"><?= formatMoney($dfd['dotacao_valor_reservado']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 9 a 13 - Finalização -->
        <div class="card fade-in mb-4">
            <div class="card-header">
                <h5><i class="bi bi-check-circle me-2"></i>Finalização</h5>
            </div>
            <div class="card-body">
                <!-- 9. Prioridade -->
                <div class="section-header">
                    <h6><span class="section-number">9</span> Grau de Prioridade da Compra</h6>
                </div>
                <p class="mb-1">
                    <?php if ($dfd['prioridade'] === 'urgente'): ?>
                        <span class="badge bg-danger fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Urgente</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6">Normal</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($dfd['prioridade_motivo'])): ?>
                <p class="mt-2"><strong>Motivo da Urgência:</strong> <?= nl2br(sanitize($dfd['prioridade_motivo'])) ?></p>
                <?php endif; ?>

                <!-- 10. Razão de Escolha -->
                <?php if (!empty($dfd['razao_escolha'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">10</span> Exclusivamente para Dispensas - Razão de Escolha do Contratado</h6>
                </div>
                <p class="mb-1">
                    <span class="badge bg-warning text-dark"><?= sanitize($razoes[$dfd['razao_escolha']] ?? $dfd['razao_escolha']) ?></span>
                </p>
                <?php if (!empty($dfd['razao_justificativa'])): ?>
                <p class="mt-2"><strong>Justificativa:</strong> <?= nl2br(sanitize($dfd['razao_justificativa'])) ?></p>
                <?php endif; ?>
                <?php endif; ?>

                <!-- 11. Vinculação -->
                <div class="section-header">
                    <h6><span class="section-number">11</span> Vinculado ou Dependente de Outro DFD</h6>
                </div>
                <?php if ($dfd['vinculado_outro_dfd'] === 'sim'): ?>
                <p class="mb-0">
                    <span class="badge bg-info">Sim</span>
                    <?php if (!empty($dfd['vinculado_dfd_numero'])): ?>
                        — DFD nº <strong><?= sanitize($dfd['vinculado_dfd_numero']) ?></strong>
                    <?php endif; ?>
                </p>
                <?php else: ?>
                <p class="mb-0"><span class="badge bg-secondary">Não</span></p>
                <?php endif; ?>

                <!-- 12. Posicionamento Conclusivo -->
                <?php if (!empty($dfd['posicionamento_conclusivo'])): ?>
                <div class="section-header">
                    <h6><span class="section-number">12</span> Posicionamento Conclusivo</h6>
                </div>
                <p class="mb-0"><?= nl2br(sanitize($dfd['posicionamento_conclusivo'])) ?></p>
                <?php endif; ?>

                <!-- 13. Modalidade -->
                <?php if (!empty($modalidades) || !empty($procedimentos)): ?>
                <div class="section-header">
                    <h6><span class="section-number">13</span> Modalidade de Licitação/Contratação Direta Pretendida</h6>
                </div>
                <div class="row g-3">
                    <?php if (!empty($modalidades)): ?>
                    <div class="col-md-8">
                        <strong class="d-block mb-2">Modalidade(s):</strong>
                        <?php foreach ($modalidades as $mod): ?>
                            <span class="badge bg-primary me-1 mb-1"><?= sanitize($modalidadesLabels[$mod] ?? $mod) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($procedimentos)): ?>
                    <div class="col-md-4">
                        <strong class="d-block mb-2">Procedimento(s) Auxiliar(es):</strong>
                        <?php foreach ($procedimentos as $proc): ?>
                            <span class="badge bg-secondary me-1 mb-1"><?= sanitize($procedimentosLabels[$proc] ?? $proc) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timestamps -->
        <div class="card fade-in mb-4">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between text-muted small">
                    <span><i class="bi bi-clock me-1"></i>Criado em: <?= date('d/m/Y H:i', strtotime($dfd['created_at'])) ?></span>
                    <span><i class="bi bi-pencil me-1"></i>Atualizado em: <?= date('d/m/Y H:i', strtotime($dfd['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <?php renderFooter(); ?>
    </div>

    <?php renderScripts(); ?>
</body>
</html>
