<?php
/**
 * Sistema DFD - Login
 */
require_once 'includes/config.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } elseif (login($email, $senha)) {
        header('Location: index.php');
        exit;
    } else {
        $erro = 'E-mail ou senha incorretos, ou usuário inativo.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema DFD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            background: #f0f2f5;
            color: #1a1a2e;
        }

        /* ── Left Panel (branding) ── */
        .login-brand {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 52%;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(ellipse at 20% 80%, rgba(59,130,246,.25) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 20%, rgba(99,102,241,.20) 0%, transparent 50%),
                linear-gradient(160deg, #0f172a 0%, #1e293b 100%);
        }

        /* subtle grid texture */
        .login-brand::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        /* decorative circles */
        .login-brand::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.04);
            top: -100px;
            right: -150px;
            pointer-events: none;
        }

        .brand-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 380px;
        }

        .brand-icon-wrap {
            width: 80px;
            height: 80px;
            border-radius: 22px;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.75rem;
            font-size: 2.2rem;
            color: #fff;
            box-shadow: 0 12px 32px rgba(59,130,246,.35);
        }

        .brand-content h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.025em;
            margin-bottom: .5rem;
        }

        .brand-content p {
            font-size: .95rem;
            color: rgba(255,255,255,.55);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .brand-features {
            display: flex;
            flex-direction: column;
            gap: .85rem;
            text-align: left;
        }

        .brand-feature {
            display: flex;
            align-items: center;
            gap: .75rem;
            color: rgba(255,255,255,.7);
            font-size: .85rem;
        }

        .brand-feature i {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255,255,255,.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .9rem;
            color: #60a5fa;
            flex-shrink: 0;
        }

        /* ── Right Panel (form) ── */
        .login-form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }

        .login-form-wrap {
            width: 100%;
            max-width: 400px;
        }

        .login-mobile-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-mobile-brand .mobile-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            margin-bottom: .75rem;
            box-shadow: 0 8px 24px rgba(59,130,246,.3);
        }

        .login-mobile-brand h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .15rem;
        }

        .login-mobile-brand p {
            font-size: .8rem;
            color: #64748b;
        }

        .form-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow:
                0 1px 3px rgba(0,0,0,.06),
                0 8px 30px rgba(0,0,0,.04);
            border: 1px solid rgba(0,0,0,.04);
        }

        .form-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: .25rem;
            color: #0f172a;
        }

        .form-card .subtitle {
            font-size: .825rem;
            color: #94a3b8;
            margin-bottom: 1.75rem;
        }

        .field {
            margin-bottom: 1.25rem;
        }

        .field label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: .95rem;
            transition: color .2s;
        }

        .input-wrap input {
            width: 100%;
            padding: .7rem .85rem .7rem 2.6rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            font-size: .9rem;
            color: #1e293b;
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .input-wrap input::placeholder { color: #cbd5e1; }

        .input-wrap input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }

        .input-wrap input:focus + i,
        .input-wrap:focus-within i { color: #3b82f6; }

        .btn-login {
            width: 100%;
            padding: .75rem;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: .9rem;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
            cursor: pointer;
            transition: transform .15s, box-shadow .25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-top: .5rem;
            box-shadow: 0 4px 14px rgba(37,99,235,.3);
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37,99,235,.4);
        }

        .btn-login:active { transform: translateY(0); }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: .7rem 1rem;
            font-size: .825rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .login-footer-text {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .75rem;
            color: #94a3b8;
        }

        .municipio-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: rgba(37,99,235,.06);
            color: #3b82f6;
            padding: .3rem .65rem;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .02em;
            margin-bottom: 1.25rem;
        }

        /* ── Responsive ── */
        @media (min-width: 960px) {
            .login-brand { display: flex; }
            .login-mobile-brand { display: none; }
        }

        @media (max-width: 480px) {
            .login-form-panel { padding: 1.25rem; }
            .form-card { padding: 1.5rem; }
        }

        /* ── Animate in ── */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .form-card  { animation: slideUp .45s ease both; }
        .brand-content { animation: slideUp .5s ease .1s both; }
    </style>
</head>
<body>

    <!-- Left branding panel (desktop) -->
    <div class="login-brand">
        <div class="brand-content">
            <div class="brand-icon-wrap">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <h1>Sistema DFD</h1>
            <p>Plataforma de gestão de Documentos de Formalização de Demanda do <?= MUNICIPIO ?>.</p>

            <div class="brand-features">
                <div class="brand-feature">
                    <i class="bi bi-pencil-square"></i>
                    <span>Crie e gerencie DFDs de forma rápida e organizada</span>
                </div>
                <div class="brand-feature">
                    <i class="bi bi-file-pdf"></i>
                    <span>Gere documentos prontos para impressão em PDF</span>
                </div>
                <div class="brand-feature">
                    <i class="bi bi-people"></i>
                    <span>Controle por centro de custo com múltiplos usuários</span>
                </div>
                <div class="brand-feature">
                    <i class="bi bi-shield-check"></i>
                    <span>Acesso seguro e rastreabilidade das demandas</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right form panel -->
    <div class="login-form-panel">
        <div class="login-form-wrap">

            <!-- Mobile brand (hidden on desktop) -->
            <div class="login-mobile-brand">
                <div class="mobile-icon"><i class="bi bi-file-earmark-text"></i></div>
                <h2>Sistema DFD</h2>
                <p><?= MUNICIPIO ?>/SC</p>
            </div>

            <div class="form-card">
                <div class="municipio-badge">
                    <i class="bi bi-building"></i>
                    <?= MUNICIPIO ?>/SC
                </div>

                <h3>Bem-vindo de volta</h3>
                <p class="subtitle">Acesse sua conta para continuar</p>

                <?php if ($erro): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <?= sanitize($erro) ?>
                </div>
                <?php endif; ?>

                <form method="POST" autocomplete="on">
                    <div class="field">
                        <label for="email">E-mail</label>
                        <div class="input-wrap">
                            <input type="email" id="email" name="email" 
                                   placeholder="seu@email.com" required autofocus
                                   value="<?= sanitize($_POST['email'] ?? '') ?>">
                            <i class="bi bi-envelope"></i>
                        </div>
                    </div>

                    <div class="field">
                        <label for="senha">Senha</label>
                        <div class="input-wrap">
                            <input type="password" id="senha" name="senha" 
                                   placeholder="Digite sua senha" required>
                            <i class="bi bi-lock"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        Entrar
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>
            </div>

            <p class="login-footer-text">
                Acesso restrito a servidores autorizados
            </p>
        </div>
    </div>

</body>
</html>
