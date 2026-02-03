<?php
/**
 * Sistema DFD - Configuração e Funções Auxiliares
 */

// ============================================================================
// Configurações
// ============================================================================
define('MUNICIPIO', 'Município');
define('APP_NAME', 'Sistema DFD');
define('DB_PATH', __DIR__ . '/../database/dfd.sqlite');

// Diretório do banco de dados
$dbDir = dirname(DB_PATH);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// Conexão com Banco de Dados (SQLite)
// ============================================================================
function getDB(): PDO {
    static $db = null;
    
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA foreign_keys = ON');
        
        // Inicializar schema se necessário
        initializeDatabase($db);
    }
    
    return $db;
}

function initializeDatabase(PDO $db): void {
    // Verificar se a tabela de usuários existe
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='usuarios'");
    if ($result->fetch()) {
        return; // Banco já inicializado
    }
    
    // Criar tabelas
    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            senha TEXT NOT NULL,
            centro_custo_nome TEXT,
            is_admin INTEGER DEFAULT 0,
            ativo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS centros_custo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL UNIQUE,
            ativo INTEGER DEFAULT 1
        );
        
        CREATE TABLE IF NOT EXISTS dfds (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            numero TEXT NOT NULL UNIQUE,
            usuario_id INTEGER,
            centro_custo TEXT,
            data_documento DATE,
            local_entrega TEXT,
            fornecedor TEXT,
            responsavel_nome TEXT,
            responsavel_matricula TEXT,
            responsavel_email TEXT,
            objeto_resumido TEXT,
            justificativa TEXT,
            prazo_entrega TEXT,
            forma_entrega TEXT,
            previsao_consumo TEXT,
            gestor_nome TEXT,
            gestor_matricula TEXT,
            fiscal_nome TEXT,
            fiscal_matricula TEXT,
            mapa_precos_qtd INTEGER DEFAULT 0,
            dotacao_numero TEXT,
            dotacao_funcional TEXT,
            dotacao_orgao TEXT,
            dotacao_acao TEXT,
            dotacao_unidade TEXT,
            dotacao_valor_reservado REAL DEFAULT 0,
            prioridade TEXT DEFAULT 'normal',
            prioridade_motivo TEXT,
            razao_escolha TEXT,
            razao_justificativa TEXT,
            vinculado_outro_dfd TEXT DEFAULT 'nao',
            vinculado_dfd_numero TEXT,
            modalidade_licitacao TEXT,
            procedimento_auxiliar TEXT,
            posicionamento_conclusivo TEXT,
            valor_total REAL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        );
        
        CREATE TABLE IF NOT EXISTS dfd_itens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            dfd_id INTEGER NOT NULL,
            item_numero INTEGER,
            especificacao TEXT,
            unidade TEXT,
            quantidade REAL DEFAULT 0,
            marca_modelo TEXT,
            valor_unitario REAL DEFAULT 0,
            valor_total REAL DEFAULT 0,
            FOREIGN KEY (dfd_id) REFERENCES dfds(id) ON DELETE CASCADE
        );
    ");
    
    // Inserir usuário admin padrão (senha: admin123)
    $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO usuarios (nome, email, senha, is_admin, ativo) VALUES ('Administrador', 'admin@sistema.com', '$senhaHash', 1, 1)");
    
    // Inserir alguns centros de custo de exemplo
    $db->exec("
        INSERT INTO centros_custo (nome, ativo) VALUES 
        ('Secretaria de Administração', 1),
        ('Secretaria de Educação', 1),
        ('Secretaria de Saúde', 1),
        ('Secretaria de Obras', 1),
        ('Secretaria de Finanças', 1)
    ");
}

// ============================================================================
// Autenticação
// ============================================================================
function login(string $email, string $senha): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_is_admin'] = (bool)$user['is_admin'];
        $_SESSION['user_centro_custo_nome'] = $user['centro_custo_nome'];
        return true;
    }
    
    return false;
}

function logout(): void {
    session_destroy();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getLoggedUser(): array {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nome' => $_SESSION['user_nome'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'is_admin' => $_SESSION['user_is_admin'] ?? false,
        'centro_custo_nome' => $_SESSION['user_centro_custo_nome'] ?? ''
    ];
}

function isAdmin(): bool {
    return $_SESSION['user_is_admin'] ?? false;
}

// ============================================================================
// Flash Messages
// ============================================================================
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ============================================================================
// Funções Auxiliares
// ============================================================================
function sanitize(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatDate(?string $date): string {
    if (empty($date)) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}

function gerarNumeroDFD(): string {
    $db = getDB();
    $ano = date('Y');
    $stmt = $db->query("SELECT COUNT(*) + 1 as seq FROM dfds WHERE strftime('%Y', created_at) = '$ano'");
    $seq = $stmt->fetchColumn();
    return sprintf('DFD-%s/%04d', $ano, $seq);
}

// ============================================================================
// Opções de Formulários
// ============================================================================
function getFormasEntrega(): array {
    return [
        'unica' => 'Entrega Única',
        'parcelada' => 'Entrega Parcelada',
        'demanda' => 'Sob Demanda'
    ];
}

function getPrevisaoConsumo(): array {
    return [
        '30' => '30 dias',
        '60' => '60 dias',
        '90' => '90 dias',
        '180' => '180 dias',
        '365' => '12 meses'
    ];
}

function getPrioridades(): array {
    return [
        'normal' => 'Normal',
        'urgente' => 'Urgente'
    ];
}

function getRazoesEscolha(): array {
    return [
        'menor_preco' => 'Menor Preço',
        'melhor_tecnica' => 'Melhor Técnica',
        'tecnica_preco' => 'Técnica e Preço',
        'maior_desconto' => 'Maior Desconto',
        'maior_retorno' => 'Maior Retorno Econômico'
    ];
}

function getModalidades(): array {
    return [
        'pregao_eletronico' => 'Pregão Eletrônico',
        'pregao_presencial' => 'Pregão Presencial',
        'concorrencia' => 'Concorrência',
        'tomada_precos' => 'Tomada de Preços',
        'convite' => 'Convite',
        'dispensa' => 'Dispensa de Licitação',
        'inexigibilidade' => 'Inexigibilidade'
    ];
}

function getProcedimentosAuxiliares(): array {
    return [
        'srp' => 'Sistema de Registro de Preços (SRP)',
        'credenciamento' => 'Credenciamento',
        'leilao' => 'Leilão',
        'dialogo_competitivo' => 'Diálogo Competitivo'
    ];
}

// ============================================================================
// Renderização de Interface
// ============================================================================
function renderHead(string $title = ''): void {
    $pageTitle = $title ? "$title - " . APP_NAME : APP_NAME;
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
        }
        
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 0.75rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .navbar-brand {
            color: #fff !important;
            font-weight: 700;
            font-size: 1.15rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.75) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 6px;
            transition: all 0.2s;
        }
        
        .nav-link:hover, .nav-link.active {
            color: #fff !important;
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1200px;
            padding: 2rem 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.25rem;
            border-radius: 12px 12px 0 0 !important;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border-color: #e2e8f0;
            padding: 0.5rem 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 0.35rem;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }
        
        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        footer {
            text-align: center;
            padding: 2rem 0;
            color: #94a3b8;
            font-size: 0.8rem;
        }
    </style>
    <?php
}

function renderNavbar(string $active = ''): void {
    $user = getLoggedUser();
    ?>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-file-earmark-text"></i>
                Sistema DFD
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'dfds' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-list-ul me-1"></i>DFDs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="novo.php">
                            <i class="bi bi-plus-circle me-1"></i>Novo DFD
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <?= sanitize($user['nome']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small text-muted"><?= sanitize($user['email']) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php
}

function renderFooter(): void {
    ?>
    <footer>
        <p><?= APP_NAME ?> &copy; <?= date('Y') ?> — <?= MUNICIPIO ?></p>
    </footer>
    <?php
}

function renderScripts(): void {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Confirmação de exclusão
        function confirmDelete(id, numero) {
            if (confirm('Tem certeza que deseja excluir o DFD ' + numero + '?\n\nEsta ação não pode ser desfeita.')) {
                window.location.href = 'excluir.php?id=' + id;
            }
        }
        
        // Máscara de dinheiro simples
        document.querySelectorAll('.money-input').forEach(function(input) {
            input.addEventListener('blur', function() {
                let value = this.value.replace(/[^\d,]/g, '').replace(',', '.');
                if (value) {
                    let num = parseFloat(value) || 0;
                    this.value = 'R$ ' + num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                }
            });
        });
    </script>
    <?php
}
