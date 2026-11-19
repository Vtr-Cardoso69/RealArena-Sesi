<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel ADM - Interclasse</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; }
        header, main { max-width: 1150px; margin: 1.5rem auto; padding: 1rem; }
        header, .card { background: #fff; border-radius: .75rem; box-shadow: 0 4px 16px #0f172a12; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
        nav { display: flex; align-items: center; gap: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .card { padding: 1.25rem; margin-bottom: 1rem; }
        label { display: block; margin-top: .65rem; }
        input, select, button { box-sizing: border-box; padding: .6rem; margin-top: .3rem; max-width: 100%; }
        input[type=text], input[type=date], input[type=time], input[type=number], select { width: 100%; }
        button { cursor: pointer; }
        .game-score { border-bottom: 1px solid #e2e8f0; padding: .8rem 0; }
        .score-input { width: 4.5rem !important; }
        .flash { padding: .8rem; border-radius: .4rem; margin-bottom: 1rem; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 760px) { .grid { grid-template-columns: 1fr; } header { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<header>
    <strong>Painel do Professor (ADM)</strong>
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
            <h2>Cadastrar sala</h2>
            <form method="post" action="index.php?page=admin">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_room">
                <label for="room_name">Nome da sala</label>
                <input id="room_name" name="nome" type="text" maxlength="100" required>
                <button type="submit">Salvar sala</button>
            </form>

            <h2>Cadastrar representante</h2>
            <form method="post" action="index.php?page=admin">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_representative">
                <label for="representative_name">Nome</label>
                <input id="representative_name" name="nome" type="text" maxlength="150" required>
                <label for="representative_rm">RM</label>
                <input id="representative_rm" name="rm" type="text" maxlength="30" required>
                <label for="representative_room">Sala</label>
                <select id="representative_room" name="id_sala" required>
                    <option value="">Selecione</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>"><?= htmlspecialchars($room['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Cadastrar representante</button>
            </form>
        </section>

        <section class="card">
            <h2>Cadastrar modalidade</h2>
            <form method="post" action="index.php?page=admin">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="create_modality">
                <label for="modality_name">Nome do esporte</label>
                <input id="modality_name" name="nome" type="text" maxlength="100" required>
                <label for="gender">Gênero</label>
                <select id="gender" name="genero" required>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Misto">Misto</option>
                </select>
                <label for="max_players">Máximo de jogadores por time</label>
                <input id="max_players" name="max_jogadores" type="number" min="1" max="1000" value="10" required>
                <label for="win_points">Pontos por vitória</label>
                <input id="win_points" name="pts_vitoria" type="number" min="0" value="3" required>
                <label for="draw_points">Pontos por empate</label>
                <input id="draw_points" name="pts_empate" type="number" min="0" value="1" required>
                <label for="loss_points">Pontos por derrota</label>
                <input id="loss_points" name="pts_derrota" type="number" min="0" value="0" required>
                <button type="submit">Salvar modalidade</button>
            </form>
            <h3>Modalidades cadastradas</h3>
            <?php if ($modalities === []): ?>
                <p>Nenhuma modalidade cadastrada.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($modalities as $modality): ?>
                        <li><?= htmlspecialchars($modality['nome']) ?> (<?= htmlspecialchars($modality['genero']) ?>) — limite <?= (int) $modality['max_jogadores'] ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2>Agendar jogo</h2>
            <form method="post" action="index.php?page=admin">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="schedule_game">
                <label for="game_modality">Modalidade</label>
                <select id="game_modality" name="id_modalidade" required>
                    <option value="">Selecione</option>
                    <?php foreach ($modalities as $modality): ?>
                        <option value="<?= (int) $modality['id'] ?>"><?= htmlspecialchars($modality['nome'] . ' (' . $modality['genero'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="room_a">Sala A</label>
                <select id="room_a" name="id_sala_a" required>
                    <option value="">Selecione</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>"><?= htmlspecialchars($room['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="room_b">Sala B</label>
                <select id="room_b" name="id_sala_b" required>
                    <option value="">Selecione</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>"><?= htmlspecialchars($room['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="game_date">Data</label>
                <input id="game_date" name="dia" type="date" required>
                <label for="game_time">Horário (opcional)</label>
                <input id="game_time" name="horario" type="time">
                <label for="court">Local / quadra</label>
                <input id="court" name="quadra" type="text" maxlength="100" required>
                <button type="submit">Agendar confronto</button>
            </form>
        </section>
    </div>

    <section class="card">
        <h2>Jogos e lançamento de placares</h2>
        <?php if ($games === []): ?>
            <p>Nenhum jogo cadastrado.</p>
        <?php else: ?>
            <?php foreach ($games as $game): ?>
                <form class="game-score" method="post" action="index.php?page=admin">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="record_score">
                    <input type="hidden" name="id_jogo" value="<?= (int) $game['id'] ?>">
                    <strong><?= htmlspecialchars($game['modalidade']) ?> (<?= htmlspecialchars($game['genero']) ?>)</strong>
                    — <?= htmlspecialchars(date('d/m/Y', strtotime($game['dia']))) ?>
                    <?= $game['horario'] ? htmlspecialchars(substr($game['horario'], 0, 5)) : '' ?>
                    · <?= htmlspecialchars($game['quadra']) ?>
                    · <?= htmlspecialchars($game['status']) ?><br>
                    <label>
                        <?= htmlspecialchars($game['sala_a']) ?>
                        <input class="score-input" type="number" name="gols_a" min="0" max="1000000" value="<?= $game['gols_pts_a'] === null ? 0 : (int) $game['gols_pts_a'] ?>" required>
                        x
                        <input class="score-input" type="number" name="gols_b" min="0" max="1000000" value="<?= $game['gols_pts_b'] === null ? 0 : (int) $game['gols_pts_b'] ?>" required>
                        <?= htmlspecialchars($game['sala_b']) ?>
                    </label>
                    <button type="submit"><?= $game['status'] === 'finalizado' ? 'Atualizar placar' : 'Salvar placar' ?></button>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
