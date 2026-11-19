<?php
require_once 'conexao.php';

$erro = '';
$etapa = 'rm'; // 'rm', 'criar_senha', 'digitar_senha'
$rmDigitado = trim($_POST['rm'] ?? '');
$usuarioEncontrado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'verificar_rm';

    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE rm = :rm LIMIT 1");
    $stmt->execute(['rm' => $rmDigitado]);
    $usuarioEncontrado = $stmt->fetch();

    if (!$usuarioEncontrado) {
        $erro = "RM não encontrado. Peça ao representante da sua sala para cadastrá-lo.";
    } else {
        if ($acao === 'verificar_rm') {
            // Verifica se a senha está vazia/NULL (primeiro acesso)
            if (empty($usuarioEncontrado['senha'])) {
                $etapa = 'criar_senha';
            } else {
                $etapa = 'digitar_senha';
            }
        } elseif ($acao === 'salvar_nova_senha') {
            $novaSenha = $_POST['nova_senha'] ?? '';
            if (strlen($novaSenha) < 4) {
                $erro = "A senha deve ter pelo menos 4 caracteres.";
                $etapa = 'criar_senha';
            } elseif (!empty($usuarioEncontrado['senha'])) {
                $erro = "Este usuário já possui senha cadastrada.";
                $etapa = 'digitar_senha';
            } else {
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE usuario SET senha = :senha WHERE id = :id");
                $upd->execute(['senha' => $hash, 'id' => $usuarioEncontrado['id']]);

                $_SESSION['usuario'] = $usuarioEncontrado;
                header("Location: index.php");
                exit;
            }
        } elseif ($acao === 'autenticar') {
            $senha = $_POST['senha'] ?? '';
            if (password_verify($senha, $usuarioEncontrado['senha'])) {
                $_SESSION['usuario'] = $usuarioEncontrado;
                header("Location: index.php");
                exit;
            } else {
                $erro = "Senha incorreta.";
                $etapa = 'digitar_senha';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: #fff; padding: 30px; border-radius: 8px; width: 340px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input, button { width: 100%; padding: 10px; margin-top: 10px; box-sizing: border-box; }
        button { background: #1e293b; color: #fff; border: none; cursor: pointer; font-weight: bold; }
        .erro { color: #dc2626; font-size: 14px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="box">
    <h2>Acesso ao Sistema</h2>
    <?php if ($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if ($etapa === 'rm'): ?>
        <form method="POST">
            <input type="hidden" name="acao" value="verificar_rm">
            <label>Digite seu RM:</label>
            <input type="text" name="rm" required autofocus>
            <button type="submit">Continuar</button>
        </form>

    <?php elseif ($etapa === 'criar_senha'): ?>
        <p>Olá, <strong><?= htmlspecialchars($usuarioEncontrado['nome']) ?></strong>! Este é seu primeiro acesso. Crie uma senha:</p>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_nova_senha">
            <input type="hidden" name="rm" value="<?= htmlspecialchars($rmDigitado) ?>">
            <input type="password" name="nova_senha" placeholder="Nova senha" required autofocus>
            <button type="submit">Salvar Senha e Entrar</button>
        </form>

    <?php elseif ($etapa === 'digitar_senha'): ?>
        <p>Olá, <strong><?= htmlspecialchars($usuarioEncontrado['nome']) ?></strong>!</p>
        <form method="POST">
            <input type="hidden" name="acao" value="autenticar">
            <input type="hidden" name="rm" value="<?= htmlspecialchars($rmDigitado) ?>">
            <input type="password" name="senha" placeholder="Sua senha" required autofocus>
            <button type="submit">Entrar</button>
        </form>
    <?php endif; ?>
    <p><a href="index.php">← Voltar para os jogos</a></p>
</div>
</body>
</html>
