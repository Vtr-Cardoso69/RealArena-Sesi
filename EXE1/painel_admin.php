<?php
require_once 'conexao.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = '';

// Ações do ADM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_sala') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO sala (nome) VALUES (:nome)");
        $stmt->execute(['nome' => trim($_POST['nome'])]);
        $msg = "Sala cadastrada!";
    }
    elseif ($acao === 'criar_modalidade') {
        $stmt = $pdo->prepare("INSERT INTO modalidade (nome, genero, max_jogadores, pts_vitoria, pts_empate, pts_derrota) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['nome']),
            $_POST['genero'],
            (int)$_POST['max_jogadores'],
            (int)$_POST['pts_vitoria'],
            (int)$_POST['pts_empate'],
            (int)$_POST['pts_derrota']
        ]);
        $msg = "Modalidade criada!";
    }
    elseif ($acao === 'criar_representante') {
        // Cadastra representante com senha vazia (NULL) para ele criar no primeiro acesso
        $stmt = $pdo->prepare("INSERT INTO usuario (nome, rm, senha, tipo, id_sala) VALUES (?, ?, NULL, 'representante', ?)");
        $stmt->execute([trim($_POST['nome']), trim($_POST['rm']), (int)$_POST['id_sala']]);
        $msg = "Representante cadastrado!";
    }
    elseif ($acao === 'criar_jogo') {
        $idMod = (int)$_POST['id_modalidade'];
        $idSalaA = (int)$_POST['id_salaA'];
        $idSalaB = (int)$_POST['id_salaB'];

        if ($idSalaA === $idSalaB) {
            $msg = "Erro: Selecione duas salas diferentes!";
        } else {
            // Garante automaticamente que o Time da Sala A e da Sala B existam para essa modalidade
            $pdo->prepare("INSERT IGNORE INTO time (id_sala, id_modalidade) VALUES (?, ?)")->execute([$idSalaA, $idMod]);
            $pdo->prepare("INSERT IGNORE INTO time (id_sala, id_modalidade) VALUES (?, ?)")->execute([$idSalaB, $idMod]);

            $stmtT = $pdo->prepare("SELECT id FROM time WHERE id_sala = ? AND id_modalidade = ?");
            $stmtT->execute([$idSalaA, $idMod]);
            $idTimeA = $stmtT->fetchColumn();

            $stmtT->execute([$idSalaB, $idMod]);
            $idTimeB = $stmtT->fetchColumn();

            $stmtJogo = $pdo->prepare("INSERT INTO jogo (id_modalidade, id_timeA, id_timeB, dia, horario, quadra) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtJogo->execute([$idMod, $idTimeA, $idTimeB, $_POST['dia'], $_POST['horario'] ?: null, trim($_POST['quadra'])]);
            $msg = "Jogo agendado com sucesso! Os representantes já podem escalar os jogadores.";
        }
    }
    elseif ($acao === 'lancar_placar') {
        $idJogo = (int)$_POST['id_jogo'];
        $golsA = (int)$_POST['gols_a'];
        $golsB = (int)$_POST['gols_b'];

        // Busca dados do jogo e regras de pontuação da modalidade
        $stmtInfo = $pdo->prepare("
            SELECT j.id_timeA, j.id_timeB, m.pts_vitoria, m.pts_empate, m.pts_derrota
            FROM jogo j
            JOIN modalidade m ON j.id_modalidade = m.id
            WHERE j.id = ?
        ");
        $stmtInfo->execute([$idJogo]);
        $info = $stmtInfo->fetch();

        if ($info) {
            if ($golsA > $golsB) {
                $vencedor = $info['id_timeA'];
                $ptsA = $info['pts_vitoria'];
                $ptsB = $info['pts_derrota'];
            } elseif ($golsB > $golsA) {
                $vencedor = $info['id_timeB'];
                $ptsA = $info['pts_derrota'];
                $ptsB = $info['pts_vitoria'];
            } else {
                // Empate: ambos ganham a mesma pontuação
                $vencedor = null;
                $ptsA = $info['pts_empate'];
                $ptsB = $info['pts_empate'];
            }

            $pdo->prepare("
                INSERT INTO placar (id_jogo, gols_pts_a, gols_pts_b, pontos_sala_a, pontos_sala_b)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE gols_pts_a = VALUES(gols_pts_a), gols_pts_b = VALUES(gols_pts_b),
                                        pontos_sala_a = VALUES(pontos_sala_a), pontos_sala_b = VALUES(pontos_sala_b)
            ")->execute([$idJogo, $golsA, $golsB, $ptsA, $ptsB]);

            $pdo->prepare("UPDATE jogo SET status = 'finalizado', id_time_vencedor = ? WHERE id = ?")
                ->execute([$vencedor, $idJogo]);

            $msg = "Placar registrado e Ranking atualizado automaticamente!";
        }
    }
}

$salas = $pdo->query("SELECT * FROM sala WHERE nome != 'Diretoria' ORDER BY nome")->fetchAll();
$modalidades = $pdo->query("SELECT * FROM modalidade ORDER BY nome, genero")->fetchAll();
$jogos = $pdo->query("
    SELECT j.*, m.nome AS mod_nome, m.genero, sA.nome AS salaA, sB.nome AS salaB, p.gols_pts_a, p.gols_pts_b
    FROM jogo j
    JOIN modalidade m ON j.id_modalidade = m.id
    JOIN time tA ON j.id_timeA = tA.id JOIN sala sA ON tA.id_sala = sA.id
    JOIN time tB ON j.id_timeB = tB.id JOIN sala sB ON tB.id_sala = sB.id
    LEFT JOIN placar p ON p.id_jogo = j.id
    ORDER BY j.dia DESC, j.horario ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel ADM - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); }
        input, select, button { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        .msg { background: #dcfce7; color: #166534; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Painel do Professor (ADM) | <a href="index.php">Ver Site</a></h1>
    <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>1. Cadastrar Sala</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_sala">
                <input type="text" name="nome" placeholder="Ex: 1° DS" required>
                <button type="submit">Salvar Sala</button>
            </form>

            <h3>2. Cadastrar Representante de Sala</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_representante">
                <input type="text" name="nome" placeholder="Nome do Representante" required>
                <input type="text" name="rm" placeholder="RM do Representante" required>
                <select name="id_sala" required>
                    <option value="">Selecione a Sala</option>
                    <?php foreach ($salas as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Criar Representante</button>
            </form>
        </div>

        <div class="card">
            <h3>3. Criar Modalidade & Pontuação</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_modalidade">
                <input type="text" name="nome" placeholder="Ex: Futsal, Vôlei, Queimada" required>
                <select name="genero" required>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Misto">Misto</option>
                </select>
                <label>Máx. Jogadores por Time: <input type="number" name="max_jogadores" value="10" required></label>
                <label>Pontos Vitória: <input type="number" name="pts_vitoria" value="3" required></label>
                <label>Pontos Empate: <input type="number" name="pts_empate" value="1" required></label>
                <label>Pontos Derrota: <input type="number" name="pts_derrota" value="0" required></label>
                <button type="submit">Criar Modalidade</button>
            </form>
        </div>

        <div class="card">
            <h3>4. Agendar Jogo (Confronto)</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar_jogo">
                <select name="id_modalidade" required>
                    <option value="">Selecione a Modalidade</option>
                    <?php foreach ($modalidades as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?> (<?= $m['genero'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="id_salaA" required>
                    <option value="">Sala A</option>
                    <?php foreach ($salas as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option><?php endforeach; ?>
                </select>
                <select name="id_salaB" required>
                    <option value="">Sala B</option>
                    <?php foreach ($salas as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nome']) ?></option><?php endforeach; ?>
                </select>
                <input type="date" name="dia" required>
                <input type="time" name="horario">
                <input type="text" name="quadra" placeholder="Local / Quadra (Ex: Quadra Principal)" required>
                <button type="submit">Criar Jogo</button>
            </form>
        </div>

        <div class="card">
            <h3>5. Lançar Placar</h3>
            <?php foreach ($jogos as $j): ?>
                <form method="POST" style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
                    <input type="hidden" name="acao" value="lancar_placar">
                    <input type="hidden" name="id_jogo" value="<?= $j['id'] ?>">
                    <strong><?= htmlspecialchars($j['mod_nome']) ?> (<?= $j['genero'] ?>)</strong> - <?= date('d/m', strtotime($j['dia'])) ?><br>
                    <?= htmlspecialchars($j['salaA']) ?> <input type="number" name="gols_a" value="<?= $j['gols_pts_a'] ?? 0 ?>" style="width:60px">
                    x
                    <input type="number" name="gols_b" value="<?= $j['gols_pts_b'] ?? 0 ?>" style="width:60px"> <?= htmlspecialchars($j['salaB']) ?>
                    <button type="submit" style="width:auto;">Salvar Placar</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
