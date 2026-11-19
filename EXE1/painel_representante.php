<?php
require_once 'conexao.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo'] !== 'representante') {
    header("Location: login.php");
    exit;
}

$rep = $_SESSION['usuario'];
$idSala = $rep['id_sala'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cadastrar_aluno') {
        $nome = trim($_POST['nome']);
        $rm = trim($_POST['rm']);
        try {
            $stmt = $pdo->prepare("INSERT INTO usuario (nome, rm, senha, tipo, id_sala) VALUES (?, ?, NULL, 'aluno', ?)");
            $stmt->execute([$nome, $rm, $idSala]);
            $msg = "Aluno $nome (RM: $rm) cadastrado na sala!";
        } catch (PDOException $e) {
            $msg = "Erro: RM já cadastrado no sistema.";
        }
    }
    elseif ($acao === 'escalar_jogador') {
        $idModalidade = (int)$_POST['id_modalidade'];
        $idUsuario = (int)$_POST['id_usuario'];

        // 1. Garante que exista o Time único da sala nesta modalidade
        $pdo->prepare("INSERT IGNORE INTO time (id_sala, id_modalidade) VALUES (?, ?)")->execute([$idSala, $idModalidade]);

        $stmtTime = $pdo->prepare("SELECT id FROM time WHERE id_sala = ? AND id_modalidade = ?");
        $stmtTime->execute([$idSala, $idModalidade]);
        $idTime = $stmtTime->fetchColumn();

        // 2. Verifica limite de jogadores da modalidade
        $stmtLimite = $pdo->prepare("SELECT max_jogadores FROM modalidade WHERE id = ?");
        $stmtLimite->execute([$idModalidade]);
        $max = (int)$stmtLimite->fetchColumn();

        $stmtAtual = $pdo->prepare("SELECT COUNT(*) FROM time_jogador WHERE id_time = ?");
        $stmtAtual->execute([$idTime]);
        $atual = (int)$stmtAtual->fetchColumn();

        if ($atual >= $max) {
            $msg = "Limite de $max jogadores atingido para esta modalidade!";
        } else {
            $pdo->prepare("INSERT IGNORE INTO time_jogador (id_time, id_usuario) VALUES (?, ?)")->execute([$idTime, $idUsuario]);
            $msg = "Jogador escalado no time da modalidade!";
        }
    }
    elseif ($acao === 'remover_jogador') {
        $pdo->prepare("DELETE FROM time_jogador WHERE id = ?")->execute([(int)$_POST['id_tj']]);
        $msg = "Jogador removido da escalação.";
    }
}

// Alunos da sala (incluindo o próprio representante, caso ele também jogue)
$stmtAlunos = $pdo->prepare("SELECT * FROM usuario WHERE id_sala = ? ORDER BY nome ASC");
$stmtAlunos->execute([$idSala]);
$alunosSala = $stmtAlunos->fetchAll();

// Modalidades que possuem jogos agendados para esta sala (ou todas disponíveis)
$modalidades = $pdo->query("SELECT * FROM modalidade ORDER BY nome, genero")->fetchAll();

// Escalações atuais da sala
$stmtEsc = $pdo->prepare("
    SELECT tj.id AS id_tj, m.nome AS modalidade, m.genero, u.nome AS aluno, u.rm
    FROM time t
    JOIN modalidade m ON t.id_modalidade = m.id
    JOIN time_jogador tj ON tj.id_time = t.id
    JOIN usuario u ON tj.id_usuario = u.id
    WHERE t.id_sala = ?
    ORDER BY m.nome, m.genero, u.nome
");
$stmtEsc->execute([$idSala]);
$escalacoes = $stmtEsc->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Representante - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); }
        input, select, button { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        .msg { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Painel do Representante | <a href="index.php">Ver Site</a></h1>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>1. Cadastrar Jogador (Aluno da Sala)</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="cadastrar_aluno">
                <input type="text" name="nome" placeholder="Nome completo do aluno" required>
                <input type="text" name="rm" placeholder="RM do aluno" required>
                <button type="submit">Cadastrar Aluno</button>
            </form>

            <h3>2. Escalar Jogador em Modalidade</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="escalar_jogador">
                <select name="id_modalidade" required>
                    <option value="">Selecione a Modalidade</option>
                    <?php foreach ($modalidades as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?> (<?= $m['genero'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="id_usuario" required>
                    <option value="">Selecione o Aluno da Sala</option>
                    <?php foreach ($alunosSala as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nome']) ?> (RM: <?= $a['rm'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Escalar no Time</button>
            </form>
        </div>

        <div class="card">
            <h3>Times e Jogadores Escalados da Minha Sala</h3>
            <?php if (empty($escalacoes)): ?>
                <p>Nenhum jogador escalado ainda.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($escalacoes as $e): ?>
                        <li style="margin-bottom: 8px;">
                            <strong><?= htmlspecialchars($e['modalidade']) ?> (<?= $e['genero'] ?>):</strong>
                            <?= htmlspecialchars($e['aluno']) ?> (RM: <?= $e['rm'] ?>)
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="acao" value="remover_jogador">
                                <input type="hidden" name="id_tj" value="<?= $e['id_tj'] ?>">
                                <button type="submit" style="width:auto; padding:2px 6px; background:#dc2626; color:#fff; border:none;">X</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
