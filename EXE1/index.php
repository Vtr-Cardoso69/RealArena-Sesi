<?php
require_once 'conexao.php';

$hoje = $_GET['data'] ?? date('Y-m-d');
$usuarioLogado = $_SESSION['usuario'] ?? null;

// 1. Ranking Geral das Salas (Soma dinâmica dos pontos obtidos nos placares)
$sqlRanking = "
    SELECT s.id, s.nome,
           COALESCE(SUM(
               CASE 
                   WHEN tA.id_sala = s.id THEN p.pontos_sala_a
                   WHEN tB.id_sala = s.id THEN p.pontos_sala_b
                   ELSE 0 
               END
           ), 0) AS total_pontos
    FROM sala s
    LEFT JOIN time tA ON tA.id_sala = s.id
    LEFT JOIN time tB ON tB.id_sala = s.id
    LEFT JOIN jogo j ON (j.id_timeA = tA.id OR j.id_timeB = tB.id) AND j.status = 'finalizado'
    LEFT JOIN placar p ON p.id_jogo = j.id
    WHERE s.nome != 'Diretoria'
    GROUP BY s.id, s.nome
    ORDER BY total_pontos DESC, s.nome ASC
";
// Nota: Para evitar duplicação no JOIN acima caso tA e tB sejam avaliados juntos, usamos UNION ALL:
$sqlRankingLimpo = "
    SELECT s.id, s.nome, COALESCE(SUM(pts.pontos), 0) AS total_pontos
    FROM sala s
    LEFT JOIN (
        SELECT tA.id_sala AS id_sala, p.pontos_sala_a AS pontos
        FROM jogo j
        JOIN time tA ON j.id_timeA = tA.id
        JOIN placar p ON p.id_jogo = j.id
        WHERE j.status = 'finalizado'
        UNION ALL
        SELECT tB.id_sala AS id_sala, p.pontos_sala_b AS pontos
        FROM jogo j
        JOIN time tB ON j.id_timeB = tB.id
        JOIN placar p ON p.id_jogo = j.id
        WHERE j.status = 'finalizado'
    ) pts ON pts.id_sala = s.id
    WHERE s.nome != 'Diretoria'
    GROUP BY s.id, s.nome
    ORDER BY total_pontos DESC, s.nome ASC
";
$ranking = $pdo->query($sqlRankingLimpo)->fetchAll();

// 2. Jogos do Dia
$stmtJogos = $pdo->prepare("
    SELECT j.*, m.nome AS modalidade, m.genero,
           sA.nome AS salaA, sB.nome AS salaB,
           p.gols_pts_a, p.gols_pts_b
    FROM jogo j
    JOIN modalidade m ON j.id_modalidade = m.id
    JOIN time tA ON j.id_timeA = tA.id
    JOIN sala sA ON tA.id_sala = sA.id
    JOIN time tB ON j.id_timeB = tB.id
    JOIN sala sB ON tB.id_sala = sB.id
    LEFT JOIN placar p ON p.id_jogo = j.id
    WHERE j.dia = :dia
    ORDER BY j.horario ASC, j.id ASC
");
$stmtJogos->execute(['dia' => $hoje]);
$jogosDia = $stmtJogos->fetchAll();

// 3. Se logado: Meus Jogos (Busca automática pelo ID do usuário logado nos times)
$meusJogos = [];
if ($usuarioLogado) {
    $stmtMeus = $pdo->prepare("
        SELECT DISTINCT j.*, m.nome AS modalidade, m.genero,
               sA.nome AS salaA, sB.nome AS salaB,
               p.gols_pts_a, p.gols_pts_b
        FROM time_jogador tj
        JOIN time t ON tj.id_time = t.id
        JOIN jogo j ON (j.id_timeA = t.id OR j.id_timeB = t.id)
        JOIN modalidade m ON j.id_modalidade = m.id
        JOIN time tA ON j.id_timeA = tA.id
        JOIN sala sA ON tA.id_sala = sA.id
        JOIN time tB ON j.id_timeB = tB.id
        JOIN sala sB ON tB.id_sala = sB.id
        LEFT JOIN placar p ON p.id_jogo = j.id
        WHERE tj.id_usuario = :uid
        ORDER BY j.dia ASC, j.horario ASC
    ");
    $stmtMeus->execute(['uid' => $usuarioLogado['id']]);
    $meusJogos = $stmtMeus->fetchAll();
}

// Função auxiliar para buscar escalação (apenas exibida se logado)
function getEscalacao(PDO $pdo, int $idTime): array {
    $stmt = $pdo->prepare("
        SELECT u.nome, u.rm 
        FROM time_jogador tj 
        JOIN usuario u ON tj.id_usuario = u.id 
        WHERE tj.id_time = :tid 
        ORDER BY u.nome ASC
    ");
    $stmt->execute(['tid' => $idTime]);
    return $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Interclasse Escolar</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f4f6f9; color: #333; }
        header { display: flex; justify-content: space-between; align-items: center; background: #1e293b; color: #fff; padding: 15px 20px; border-radius: 8px; }
        header a { color: #38bdf8; text-decoration: none; margin-left: 15px; font-weight: bold; }
        .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; }
        th { background: #f8fafc; }
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 12px; background: #e0f2fe; color: #0369a1; }
        .escalacao { font-size: 13px; background: #f8fafc; padding: 8px; border-radius: 4px; margin-top: 5px; }
    </style>
</head>
<body>

<header>
    <div><strong>🏆 Sistema Interclasse</strong></div>
    <div>
        <?php if ($usuarioLogado): ?>
            <span>Olá, <?= htmlspecialchars($usuarioLogado['nome']) ?> (<?= strtoupper($usuarioLogado['tipo']) ?>)</span>
            <?php if ($usuarioLogado['tipo'] === 'admin'): ?>
                <a href="painel_admin.php">Painel ADM</a>
            <?php elseif ($usuarioLogado['tipo'] === 'representante'): ?>
                <a href="painel_representante.php">Painel Representante</a>
            <?php endif; ?>
            <a href="logout.php">Sair</a>
        <?php else: ?>
            <a href="login.php">Entrar com RM</a>
        <?php endif; ?>
    </div>
</header>

<div class="grid">
    <div>
        <!-- JOGOS DO DIA -->
        <div class="card">
            <h2>📅 Jogos do Dia</h2>
            <form method="GET" style="margin-bottom: 10px;">
                <label>Filtrar data: <input type="date" name="data" value="<?= htmlspecialchars($hoje) ?>"></label>
                <button type="submit">Ver</button>
            </form>

            <?php if (empty($jogosDia)): ?>
                <p>Nenhum jogo cadastrado para esta data.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Hora / Quadra</th>
                            <th>Modalidade</th>
                            <th>Confronto & Placar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jogosDia as $j): ?>
                            <tr>
                                <td><?= $j['horario'] ? substr($j['horario'], 0, 5) : '--:--' ?><br><small><?= htmlspecialchars($j['quadra']) ?></small></td>
                                <td><?= htmlspecialchars($j['modalidade']) ?> <span class="badge"><?= $j['genero'] ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($j['salaA']) ?></strong> 
                                    [ <?= $j['status'] === 'finalizado' ? $j['gols_pts_a'] : '-' ?> ] 
                                    x 
                                    [ <?= $j['status'] === 'finalizado' ? $j['gols_pts_b'] : '-' ?> ] 
                                    <strong><?= htmlspecialchars($j['salaB']) ?></strong>

                                    <?php if ($usuarioLogado): ?>
                                        <details class="escalacao">
                                            <summary>Ver jogadores escalados</summary>
                                            <p><strong><?= htmlspecialchars($j['salaA']) ?>:</strong> 
                                                <?= implode(', ', array_column(getEscalacao($pdo, $j['id_timeA']), 'nome')) ?: 'Sem escalação' ?>
                                            </p>
                                            <p><strong><?= htmlspecialchars($j['salaB']) ?>:</strong> 
                                                <?= implode(', ', array_column(getEscalacao($pdo, $j['id_timeB']), 'nome')) ?: 'Sem escalação' ?>
                                            </p>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$usuarioLogado): ?>
                    <p><small>🔒 <em>Faça login com seu RM para ver os jogadores escalados e seus próprios jogos.</em></small></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- MEUS JOGOS (APENAS LOGADO) -->
        <?php if ($usuarioLogado): ?>
            <div class="card">
                <h2>⚽ Meus Jogos Escalados</h2>
                <?php if (empty($meusJogos)): ?>
                    <p>Você ainda não foi escalado em nenhum jogo.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Modalidade</th>
                                <th>Partida</th>
                                <th>Local</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meusJogos as $mj): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($mj['dia'])) ?> <?= $mj['horario'] ? substr($mj['horario'], 0, 5) : '' ?></td>
                                    <td><?= htmlspecialchars($mj['modalidade']) ?> (<?= $mj['genero'] ?>)</td>
                                    <td><?= htmlspecialchars($mj['salaA']) ?> x <?= htmlspecialchars($mj['salaB']) ?></td>
                                    <td><?= htmlspecialchars($mj['quadra']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RANKING GERAL -->
    <div>
        <div class="card">
            <h2>🏅 Ranking das Salas</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sala</th>
                        <th>Pontos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ranking as $idx => $r): ?>
                        <tr>
                            <td><?= $idx + 1 ?>º</td>
                            <td><strong><?= htmlspecialchars($r['nome']) ?></strong></td>
                            <td><?= $r['total_pontos'] ?> pts</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
