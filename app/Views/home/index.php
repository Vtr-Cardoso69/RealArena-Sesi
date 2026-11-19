<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interclasse Escolar</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; }
        header, main { max-width: 1100px; margin: 1.5rem auto; padding: 1rem; }
        header, .card { background: #fff; border-radius: .75rem; box-shadow: 0 4px 16px #0f172a12; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        nav { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .layout { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 1rem; }
        .card { padding: 1.25rem; margin-bottom: 1rem; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .65rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
        button { padding: .5rem .8rem; cursor: pointer; }
        .badge { display: inline-block; padding: .2rem .45rem; border-radius: .3rem; background: #e0f2fe; color: #075985; font-size: .85rem; }
        .lineup { margin-top: .5rem; font-size: .9rem; }
        .error { color: #b91c1c; }
        @media (max-width: 760px) { .layout { grid-template-columns: 1fr; } header { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<header>
    <strong>Interclasse Escolar</strong>
    <nav>
        <?php if (is_array($user)): ?>
            <span>Olá, <?= htmlspecialchars($user['nome']) ?> (<?= htmlspecialchars(strtoupper($user['tipo'])) ?>)</span>
            <?php if ($user['tipo'] === 'admin'): ?>
                <a href="index.php?page=admin">Área ADM</a>
            <?php elseif ($user['tipo'] === 'representante'): ?>
                <a href="index.php?page=representante">Área do representante</a>
            <?php endif; ?>
            <form method="post" action="index.php?page=logout">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <button type="submit">Sair</button>
            </form>
        <?php else: ?>
            <a href="index.php?page=login">Entrar com RM</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <div class="layout">
        <div>
            <section class="card">
                <h1>Jogos do dia</h1>
                <form method="get" action="index.php?page=home">
                    <label for="data">Filtrar por data:</label>
                    <input id="data" type="date" name="data" value="<?= htmlspecialchars($date) ?>" required>
                    <button type="submit">Buscar</button>
                </form>
                <?php if ($dateError !== ''): ?>
                    <p class="error" role="alert"><?= htmlspecialchars($dateError) ?></p>
                <?php endif; ?>

                <?php if ($gamesOfDay === []): ?>
                    <p>Nenhum jogo cadastrado para esta data.</p>
                <?php else: ?>
                    <table>
                        <thead>
                        <tr><th>Horário / Local</th><th>Modalidade</th><th>Confronto / Placar</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($gamesOfDay as $game): ?>
                            <tr>
                                <td>
                                    <?= $game['horario'] ? htmlspecialchars(substr($game['horario'], 0, 5)) : '--:--' ?><br>
                                    <small><?= htmlspecialchars($game['quadra']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($game['modalidade']) ?><br><span class="badge"><?= htmlspecialchars($game['genero']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($game['sala_a']) ?></strong>
                                    [<?= $game['status'] === 'finalizado' ? (int) $game['gols_pts_a'] : '-' ?>]
                                    x
                                    [<?= $game['status'] === 'finalizado' ? (int) $game['gols_pts_b'] : '-' ?>]
                                    <strong><?= htmlspecialchars($game['sala_b']) ?></strong>
                                    <?php if (is_array($user)): ?>
                                        <details class="lineup">
                                            <summary>Ver jogadores escalados</summary>
                                            <p><strong><?= htmlspecialchars($game['sala_a']) ?>:</strong>
                                                <?= $game['lineup_a'] === [] ? 'Sem escalação' : htmlspecialchars(implode(', ', array_column($game['lineup_a'], 'nome'))) ?>
                                            </p>
                                            <p><strong><?= htmlspecialchars($game['sala_b']) ?>:</strong>
                                                <?= $game['lineup_b'] === [] ? 'Sem escalação' : htmlspecialchars(implode(', ', array_column($game['lineup_b'], 'nome'))) ?>
                                            </p>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!is_array($user)): ?>
                        <p><small><a href="index.php?page=login">Entre com seu RM</a> para ver as escalações e seus jogos.</small></p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <?php if (is_array($user)): ?>
                <section class="card">
                    <h2>Meus jogos escalados</h2>
                    <?php if ($myGames === []): ?>
                        <p>Você ainda não foi escalado em nenhum jogo.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Data / Horário</th><th>Modalidade</th><th>Confronto</th><th>Local</th></tr></thead>
                            <tbody>
                            <?php foreach ($myGames as $game): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($game['dia']))) ?>
                                        <?= $game['horario'] ? htmlspecialchars(substr($game['horario'], 0, 5)) : '' ?></td>
                                    <td><?= htmlspecialchars($game['modalidade']) ?> (<?= htmlspecialchars($game['genero']) ?>)</td>
                                    <td><?= htmlspecialchars($game['sala_a']) ?> x <?= htmlspecialchars($game['sala_b']) ?></td>
                                    <td><?= htmlspecialchars($game['quadra']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>

        <aside class="card">
            <h2>Ranking das salas</h2>
            <?php if ($ranking === []): ?>
                <p>Nenhuma sala cadastrada.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>Sala</th><th>Pontos</th></tr></thead>
                    <tbody>
                    <?php foreach ($ranking as $position => $room): ?>
                        <tr>
                            <td><?= $position + 1 ?>º</td>
                            <td><?= htmlspecialchars($room['nome']) ?></td>
                            <td><?= (int) $room['total_pontos'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </aside>
    </div>
</main>
</body>
</html>
