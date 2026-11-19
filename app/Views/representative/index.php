<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do Representante - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; }
        header, main { max-width: 1100px; margin: 1.5rem auto; padding: 1rem; }
        header, .card { background: #fff; border-radius: .75rem; box-shadow: 0 4px 16px #0f172a12; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        nav { display: flex; align-items: center; gap: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .card { padding: 1.25rem; margin-bottom: 1rem; }
        label { display: block; margin-top: .65rem; }
        input, select, button { box-sizing: border-box; padding: .6rem; margin-top: .3rem; max-width: 100%; }
        input[type=text], select { width: 100%; }
        button { cursor: pointer; }
        .flash { padding: .8rem; border-radius: .4rem; margin-bottom: 1rem; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        .roster { border-bottom: 1px solid #e2e8f0; padding: .6rem 0; }
        .remove-form { display: inline; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } header { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<header>
    <strong>Representante — <?= htmlspecialchars($roomName) ?></strong>
    <nav>
        <a href="index.php?page=home">Ver site</a>
        <form method="post" action="index.php?page=logout">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit">Sair</button>
        </form>
    </nav>
</header>
<main>
    <?php if (is_array($flash)): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <section class="card">
            <h2>Cadastrar aluno da sala</h2>
            <form method="post" action="index.php?page=representante">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_student">
                <label for="student_name">Nome completo</label>
                <input id="student_name" name="nome" type="text" maxlength="150" required>
                <label for="student_rm">RM</label>
                <input id="student_rm" name="rm" type="text" maxlength="30" required>
                <button type="submit">Cadastrar aluno</button>
            </form>

            <h2>Escalar jogador</h2>
            <form method="post" action="index.php?page=representante">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="add_player">
                <label for="modality_id">Modalidade</label>
                <select id="modality_id" name="id_modalidade" required>
                    <option value="">Selecione</option>
                    <?php foreach ($modalities as $modality): ?>
                        <option value="<?= (int) $modality['id'] ?>">
                            <?= htmlspecialchars($modality['nome'] . ' (' . $modality['genero'] . ') — limite ' . $modality['max_jogadores']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="player_id">Aluno ou representante da sala</label>
                <select id="player_id" name="id_usuario" required>
                    <option value="">Selecione</option>
                    <?php foreach ($players as $player): ?>
                        <option value="<?= (int) $player['id'] ?>">
                            <?= htmlspecialchars($player['nome'] . ' (RM: ' . $player['rm'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Escalar jogador</button>
            </form>
            <p><small>O servidor valida se o jogador pertence à sua sala e se ainda há vagas no time.</small></p>
        </section>

        <section class="card">
            <h2>Times e escalações da sala</h2>
            <?php if ($roster === []): ?>
                <p>Nenhuma modalidade cadastrada.</p>
            <?php else: ?>
                <?php foreach ($roster as $modality): ?>
                    <div class="roster">
                        <strong><?= htmlspecialchars($modality['nome']) ?> (<?= htmlspecialchars($modality['genero']) ?>)</strong>
                        — <?= count($modality['jogadores']) ?>/<?= $modality['limite'] ?> jogadores
                        <?php if ($modality['jogadores'] === []): ?>
                            <p><small>Nenhum jogador escalado.</small></p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($modality['jogadores'] as $player): ?>
                                    <li>
                                        <?= htmlspecialchars($player['nome']) ?>
                                        (RM: <?= htmlspecialchars($player['rm']) ?>)
                                        <form class="remove-form" method="post" action="index.php?page=representante">
                                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="remove_player">
                                            <input type="hidden" name="id_escalacao" value="<?= (int) $player['id'] ?>">
                                            <button type="submit" aria-label="Remover jogador da escalação">Remover</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
