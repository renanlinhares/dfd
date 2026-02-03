<?php
/**
 * Sistema DFD - Gerar PDF
 * Gera versão para impressão em HTML (com suporte a DOMPDF se disponível)
 */
require_once 'includes/config.php';
requireAuth();

$db = getDB();

// Verificar ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('DFD não encontrado.');
}

// Buscar DFD
$stmt = $db->prepare("SELECT * FROM dfds WHERE id = ?");
$stmt->execute([$id]);
$dfd = $stmt->fetch();

if (!$dfd) {
    die('DFD não encontrado.');
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

// Funções auxiliares para checkboxes no PDF
function checkbox($checked) {
    return $checked ? '☑' : '☐';
}

// Gerar HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DFD <?= sanitize($dfd['numero']) ?> - <?= MUNICIPIO ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm 15mm 20mm 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
            background: #fff;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
        }
        
        .header h1 {
            font-size: 14pt;
            color: #2563eb;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .header h2 {
            font-size: 11pt;
            color: #555;
            font-weight: normal;
        }
        
        .header .numero {
            font-size: 12pt;
            font-weight: bold;
            color: #2563eb;
            margin-top: 5px;
            background: #eef2ff;
            display: inline-block;
            padding: 3px 15px;
            border-radius: 4px;
        }
        
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        
        .info-box table {
            width: 100%;
        }
        
        .info-box td {
            padding: 3px 5px;
            vertical-align: top;
        }
        
        .info-box .label {
            color: #666;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-box .value {
            font-weight: 600;
            font-size: 10pt;
        }
        
        .section {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: #fff;
            font-size: 9pt;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .section-title .num {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            width: 18px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 50%;
            margin-right: 5px;
            font-size: 9pt;
        }
        
        .section-content {
            padding: 4px 6px;
            font-size: 10pt;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
            font-size: 9pt;
        }
        
        .items-table th {
            background: #2563eb;
            color: #fff;
            font-size: 8pt;
            font-weight: 600;
            padding: 5px 6px;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .items-table th:first-child {
            border-radius: 4px 0 0 0;
        }
        
        .items-table th:last-child {
            border-radius: 0 4px 0 0;
        }
        
        .items-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .items-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        
        .items-table .text-right {
            text-align: right;
        }
        
        .items-table .text-center {
            text-align: center;
        }
        
        .items-table tfoot td {
            background: #eef2ff !important;
            font-weight: 700;
            font-size: 10pt;
            border-top: 2px solid #2563eb;
        }
        
        .check-row {
            padding: 2px 0;
        }
        
        .check-row span {
            font-size: 13pt;
            vertical-align: middle;
            margin-right: 3px;
        }
        
        .dotacao-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }
        
        .dotacao-table th, .dotacao-table td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            text-align: center;
        }
        
        .dotacao-table th {
            background: #f1f5f9;
            font-size: 8pt;
            text-transform: uppercase;
            color: #555;
        }
        
        .signature-area {
            margin-top: 40px;
            padding-top: 15px;
        }
        
        .signature-table {
            width: 100%;
        }
        
        .signature-table td {
            text-align: center;
            padding: 0 15px;
            vertical-align: bottom;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            padding-top: 4px;
            font-size: 9pt;
            margin-top: 50px;
        }
        
        .signature-name {
            font-weight: 600;
        }
        
        .signature-role {
            color: #666;
            font-size: 8pt;
        }
        
        .footer {
            text-align: center;
            font-size: 8pt;
            color: #999;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        
        .no-print {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
        }
        
        .no-print button {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 30px;
            font-size: 12pt;
            border-radius: 6px;
            cursor: pointer;
            margin: 0 5px;
        }
        
        .no-print button:hover {
            background: #1d4ed8;
        }
        
        .no-print button.secondary {
            background: #6b7280;
        }
        
        .no-print button.secondary:hover {
            background: #4b5563;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                font-size: 9pt;
            }
        }
    </style>
</head>
<body>
    <!-- Botões de ação (não impressos) -->
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
        <button class="secondary" onclick="window.close()">✕ Fechar</button>
    </div>

    <!-- Cabeçalho -->
    <div class="header">
        <h1>Documento de Formalização de Demanda</h1>
        <h2><?= MUNICIPIO ?>/SC</h2>
        <div class="numero">DFD Nº <?= sanitize($dfd['numero']) ?></div>
    </div>

    <!-- Informações Gerais -->
    <div class="info-box">
        <table>
            <tr>
                <td style="width: 50%;">
                    <div class="label">Centro de Custo</div>
                    <div class="value"><?= sanitize($dfd['centro_custo']) ?></div>
                </td>
                <td style="width: 25%;">
                    <div class="label">Data</div>
                    <div class="value"><?= formatDate($dfd['data_documento']) ?></div>
                </td>
                <td style="width: 25%;">
                    <div class="label">Valor Total</div>
                    <div class="value" style="color: #16a34a;"><?= formatMoney($dfd['valor_total']) ?></div>
                </td>
            </tr>
            <?php if (!empty($dfd['fornecedor']) || !empty($dfd['local_entrega'])): ?>
            <tr>
                <?php if (!empty($dfd['fornecedor'])): ?>
                <td>
                    <div class="label">Fornecedor/Contratado</div>
                    <div class="value"><?= sanitize($dfd['fornecedor']) ?></div>
                </td>
                <?php endif; ?>
                <?php if (!empty($dfd['local_entrega'])): ?>
                <td colspan="<?= empty($dfd['fornecedor']) ? 3 : 2 ?>">
                    <div class="label">Local de Entrega</div>
                    <div class="value"><?= sanitize($dfd['local_entrega']) ?></div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endif; ?>
            <tr>
                <td>
                    <div class="label">Responsável pela Demanda</div>
                    <div class="value"><?= sanitize($dfd['responsavel_nome']) ?></div>
                </td>
                <td>
                    <div class="label">Matrícula</div>
                    <div class="value"><?= sanitize($dfd['responsavel_matricula']) ?></div>
                </td>
                <td>
                    <div class="label">E-mail</div>
                    <div class="value"><?= sanitize($dfd['responsavel_email']) ?></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 1. Objeto Resumido -->
    <div class="section">
        <div class="section-title"><span class="num">1</span> Objeto Resumido</div>
        <div class="section-content"><?= nl2br(sanitize($dfd['objeto_resumido'])) ?></div>
    </div>

    <!-- 2. Justificativa -->
    <div class="section">
        <div class="section-title"><span class="num">2</span> Justificativa da Necessidade da Contratação/Motivação</div>
        <div class="section-content"><?= nl2br(sanitize($dfd['justificativa'])) ?></div>
    </div>

    <!-- 3. Prazo de Entrega -->
    <div class="section">
        <div class="section-title"><span class="num">3</span> Prazo de Entrega, Local e Horário</div>
        <div class="section-content"><?= !empty($dfd['prazo_entrega']) ? nl2br(sanitize($dfd['prazo_entrega'])) : '<em style="color:#999;">Não informado</em>' ?></div>
    </div>

    <!-- 4. Forma de Entrega -->
    <div class="section">
        <div class="section-title"><span class="num">4</span> Forma de Entrega</div>
        <div class="section-content">
            <?php foreach ($formasEntrega as $key => $label): ?>
            <div class="check-row">
                <span><?= checkbox($dfd['forma_entrega'] === $key) ?></span> <?= $label ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 5. Previsão de Consumo -->
    <div class="section">
        <div class="section-title"><span class="num">5</span> Previsão de Consumo</div>
        <div class="section-content">
            <?php foreach ($previsoes as $key => $label): ?>
            <div class="check-row" style="display: inline-block; margin-right: 15px;">
                <span><?= checkbox($dfd['previsao_consumo'] === $key) ?></span> <?= $label ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 6. Gestão e Fiscalização -->
    <div class="section">
        <div class="section-title"><span class="num">6</span> Gestão e Fiscalização Contratual</div>
        <div class="section-content">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 50%; padding-right: 10px;">
                        <strong>Gestor:</strong> <?= sanitize($dfd['gestor_nome']) ?>
                        <?php if (!empty($dfd['gestor_matricula'])): ?>
                            (Mat. <?= sanitize($dfd['gestor_matricula']) ?>)
                        <?php endif; ?>
                    </td>
                    <td style="width: 50%;">
                        <strong>Fiscal:</strong> <?= sanitize($dfd['fiscal_nome']) ?>
                        <?php if (!empty($dfd['fiscal_matricula'])): ?>
                            (Mat. <?= sanitize($dfd['fiscal_matricula']) ?>)
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 7. Detalhamento do Objeto -->
    <div class="section">
        <div class="section-title"><span class="num">7</span> Detalhamento do Objeto e Estimativa de Preço</div>
        <div class="section-content">
            <?php if (!empty($itens)): ?>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 40px;">Item</th>
                        <th>Especificação</th>
                        <th class="text-center" style="width: 50px;">Und.</th>
                        <th class="text-right" style="width: 60px;">Qtd.</th>
                        <th style="width: 100px;">Marca/Modelo</th>
                        <th class="text-right" style="width: 90px;">V. Unitário</th>
                        <th class="text-right" style="width: 90px;">V. Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $item): ?>
                    <tr>
                        <td class="text-center"><?= intval($item['item_numero']) ?></td>
                        <td><?= sanitize($item['especificacao']) ?></td>
                        <td class="text-center"><?= sanitize($item['unidade']) ?></td>
                        <td class="text-right"><?= number_format($item['quantidade'], $item['quantidade'] == intval($item['quantidade']) ? 0 : 4, ',', '.') ?></td>
                        <td><?= sanitize($item['marca_modelo']) ?></td>
                        <td class="text-right"><?= formatMoney($item['valor_unitario']) ?></td>
                        <td class="text-right"><?= formatMoney($item['valor_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="text-right">TOTAL GERAL:</td>
                        <td class="text-right"><?= formatMoney($dfd['valor_total']) ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <em style="color:#999;">Nenhum item cadastrado.</em>
            <?php endif; ?>

            <?php if ($dfd['mapa_precos_qtd'] > 0): ?>
            <div style="margin-top: 8px; padding: 5px; background: #f8fafc; border-radius: 3px;">
                <strong>7.1 Do Mapa de Preços:</strong> O mapa de preços foi formado por ao menos <strong><?= intval($dfd['mapa_precos_qtd']) ?></strong> pesquisas de preços realizadas na forma estabelecida no Decreto Municipal.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 8. Dotação Orçamentária -->
    <div class="section">
        <div class="section-title"><span class="num">8</span> Dotação Orçamentária</div>
        <div class="section-content">
            <table class="dotacao-table">
                <thead>
                    <tr>
                        <th>Nº Dotação</th>
                        <th>Funcional</th>
                        <th>Órgão</th>
                        <th>Ação</th>
                        <th>Unidade</th>
                        <th>Valor Reservado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= sanitize($dfd['dotacao_numero']) ?: '—' ?></td>
                        <td><?= sanitize($dfd['dotacao_funcional']) ?: '—' ?></td>
                        <td><?= sanitize($dfd['dotacao_orgao']) ?: '—' ?></td>
                        <td><?= sanitize($dfd['dotacao_acao']) ?: '—' ?></td>
                        <td><?= sanitize($dfd['dotacao_unidade']) ?: '—' ?></td>
                        <td><?= $dfd['dotacao_valor_reservado'] > 0 ? formatMoney($dfd['dotacao_valor_reservado']) : '—' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 9. Prioridade -->
    <div class="section">
        <div class="section-title"><span class="num">9</span> Grau de Prioridade da Compra</div>
        <div class="section-content">
            <?php foreach ($prioridades as $key => $label): ?>
            <div class="check-row" style="display: inline-block; margin-right: 20px;">
                <span><?= checkbox($dfd['prioridade'] === $key) ?></span> <?= $label ?>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($dfd['prioridade_motivo'])): ?>
            <div style="margin-top: 5px;">
                <strong>Motivo da Urgência:</strong> <?= nl2br(sanitize($dfd['prioridade_motivo'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 10. Razão de Escolha -->
    <div class="section">
        <div class="section-title"><span class="num">10</span> Exclusivamente para Dispensas de Licitação - Razão de Escolha do Contratado</div>
        <div class="section-content">
            <?php foreach ($razoes as $key => $label): ?>
            <div class="check-row">
                <span><?= checkbox($dfd['razao_escolha'] === $key) ?></span> <?= $label ?>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($dfd['razao_justificativa'])): ?>
            <div style="margin-top: 5px;">
                <strong>Justificativa:</strong> <?= nl2br(sanitize($dfd['razao_justificativa'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 11. Vinculação -->
    <div class="section">
        <div class="section-title"><span class="num">11</span> Vinculado ou Dependente de Outro DFD</div>
        <div class="section-content">
            <div class="check-row" style="display: inline-block; margin-right: 20px;">
                <span><?= checkbox($dfd['vinculado_outro_dfd'] === 'sim') ?></span> Sim
            </div>
            <div class="check-row" style="display: inline-block; margin-right: 20px;">
                <span><?= checkbox($dfd['vinculado_outro_dfd'] !== 'sim') ?></span> Não
            </div>
            <?php if ($dfd['vinculado_outro_dfd'] === 'sim' && !empty($dfd['vinculado_dfd_numero'])): ?>
            <span style="margin-left: 10px;">— DFD nº <strong><?= sanitize($dfd['vinculado_dfd_numero']) ?></strong></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 12. Posicionamento Conclusivo -->
    <div class="section">
        <div class="section-title"><span class="num">12</span> Posicionamento Conclusivo</div>
        <div class="section-content">
            <?= !empty($dfd['posicionamento_conclusivo']) ? nl2br(sanitize($dfd['posicionamento_conclusivo'])) : '<em style="color:#999;">Não informado</em>' ?>
        </div>
    </div>

    <!-- 13. Modalidade -->
    <div class="section">
        <div class="section-title"><span class="num">13</span> Modalidade de Licitação/Contratação Direta Pretendida</div>
        <div class="section-content">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 65%; vertical-align: top;">
                        <strong style="font-size: 8pt; text-transform: uppercase; color: #666;">Modalidade:</strong><br>
                        <?php foreach ($modalidadesLabels as $key => $label): ?>
                        <div class="check-row">
                            <span><?= checkbox(in_array($key, $modalidades)) ?></span> <?= $label ?>
                        </div>
                        <?php endforeach; ?>
                    </td>
                    <td style="width: 35%; vertical-align: top;">
                        <strong style="font-size: 8pt; text-transform: uppercase; color: #666;">Procedimento Auxiliar:</strong><br>
                        <?php foreach ($procedimentosLabels as $key => $label): ?>
                        <div class="check-row">
                            <span><?= checkbox(in_array($key, $procedimentos)) ?></span> <?= $label ?>
                        </div>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Área de Assinatura -->
    <div class="signature-area">
        <table class="signature-table">
            <tr>
                <td style="width: 50%;">
                    <div class="signature-line">
                        <div class="signature-name"><?= sanitize($dfd['responsavel_nome']) ?></div>
                        <div class="signature-role">Responsável pela Demanda</div>
                        <?php if (!empty($dfd['responsavel_matricula'])): ?>
                        <div class="signature-role">Mat. <?= sanitize($dfd['responsavel_matricula']) ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="width: 50%;">
                    <div class="signature-line">
                        <div class="signature-name">&nbsp;</div>
                        <div class="signature-role">Autoridade Competente</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Rodapé -->
    <div class="footer">
        <?= MUNICIPIO ?>/SC — Documento gerado em <?= date('d/m/Y \à\s H:i') ?> — Sistema DFD
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Tentar usar DOMPDF se disponível
$dompdfPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($dompdfPath)) {
    require_once $dompdfPath;
    
    if (class_exists('Dompdf\Dompdf')) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'DFD_' . str_replace('/', '-', $dfd['numero']) . '.pdf';
        $dompdf->stream($filename, ['Attachment' => false]);
        exit;
    }
}

// Fallback: exibir HTML para impressão
echo $html;
