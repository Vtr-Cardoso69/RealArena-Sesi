<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; display: grid; min-height: 100vh; place-items: center; margin: 0; color: #0f172a; }
        main { background: #fff; padding: 2rem; border-radius: .75rem; width: min(22rem, calc(100% - 4rem)); box-shadow: 0 8px 24px #0f172a1a; }
        label { display: block; margin-top: 1rem; }
        input, button { box-sizing: border-box; width: 100%; padding: .75rem; margin-top: .4rem; }
        button { border: 0; border-radius: .35rem; background: #1e293b; color: #fff; font-weight: bold; cursor: pointer; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    <h1>Acesso ao Interclasse</h1>
    <?php if ($error !== ''): ?>
        <p class="error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($stage === 'rm'): ?>
        <form method="post" action="index.php?page=login">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="identify">
            <label for="rm">RM</label>
            <input id="rm" name="rm" type="text" maxlength="30" value="<?= htmlspecialchars($rm) ?>" required autofocus>
            <button type="submit">Continuar</button>
        </form>
    <?php elseif ($stage === 'create_password'): ?>
        <p>Olá, <strong><?= htmlspecialchars($name) ?></strong>. Crie uma senha para seu primeiro acesso.</p>
        <form method="post" action="index.php?page=login">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="create_password">
            <input type="hidden" name="rm" value="<?= htmlspecialchars($rm) ?>">
            <label for="password">Nova senha (mínimo 8 caracteres)</label>
            <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required autofocus>
            <label for="password_confirmation">Confirme a senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Criar senha e entrar</button>
        </form>
    <?php else: ?>
        <p>Olá, <strong><?= htmlspecialchars($name) ?></strong>. Informe sua senha.</p>
        <form method="post" action="index.php?page=login">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action" value="authenticate">
            <input type="hidden" name="rm" value="<?= htmlspecialchars($rm) ?>">
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
            <button type="submit">Entrar</button>
        </form>
    <?php endif; ?>

    <p><a href="index.php?page=home">Voltar para o início</a></p>
</main>
</body>
</html>
