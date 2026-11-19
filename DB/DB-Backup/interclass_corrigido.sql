-- Banco de Dados: interclass (Versão Refatorada e Normalizada)
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "-03:00";

CREATE DATABASE IF NOT EXISTS `interclass` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `interclass`;

-- 1. Tabela de Salas (Turmas)
CREATE TABLE `sala` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sala_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela de Usuários (ADM, Representante, Aluno)
CREATE TABLE `usuario` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `rm` VARCHAR(30) NOT NULL,
  `senha` VARCHAR(255) NULL DEFAULT NULL COMMENT 'NULL = primeiro acesso pendente',
  `tipo` ENUM('admin', 'representante', 'aluno') NOT NULL DEFAULT 'aluno',
  `id_sala` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_rm` (`rm`),
  KEY `fk_usuario_sala` (`id_sala`),
  CONSTRAINT `fk_usuario_sala` FOREIGN KEY (`id_sala`) REFERENCES `sala` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela de Modalidades (Esporte + Gênero + Limite de Jogadores)
CREATE TABLE `modalidade` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(100) NOT NULL,
  `genero` ENUM('Masculino', 'Feminino', 'Misto') NOT NULL,
  `max_jogadores` INT(11) NOT NULL DEFAULT 10,
  `pts_vitoria` INT(11) NOT NULL DEFAULT 3,
  `pts_empate` INT(11) NOT NULL DEFAULT 1,
  `pts_derrota` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_modalidade_genero` (`nome`, `genero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabela de Times (Regra: 1 Time por Sala em cada Modalidade/Gênero)
CREATE TABLE `time` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_sala` INT(11) NOT NULL,
  `id_modalidade` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_time_sala_modalidade` (`id_sala`, `id_modalidade`),
  KEY `fk_time_modalidade` (`id_modalidade`),
  CONSTRAINT `fk_time_sala` FOREIGN KEY (`id_sala`) REFERENCES `sala` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_time_modalidade` FOREIGN KEY (`id_modalidade`) REFERENCES `modalidade` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabela Pivô: Jogadores escalados em cada Time
CREATE TABLE `time_jogador` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_time` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_time_usuario` (`id_time`, `id_usuario`),
  KEY `fk_tj_usuario` (`id_usuario`),
  CONSTRAINT `fk_tj_time` FOREIGN KEY (`id_time`) REFERENCES `time` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tj_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabela de Jogos (Partidas entre Time A e Time B)
CREATE TABLE `jogo` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_modalidade` INT(11) NOT NULL,
  `id_timeA` INT(11) NOT NULL,
  `id_timeB` INT(11) NOT NULL,
  `dia` DATE NOT NULL,
  `horario` TIME NULL DEFAULT NULL,
  `quadra` VARCHAR(100) NOT NULL,
  `status` ENUM('agendado', 'finalizado') NOT NULL DEFAULT 'agendado',
  `id_time_vencedor` INT(11) NULL DEFAULT NULL COMMENT 'NULL se agendado ou empate',
  PRIMARY KEY (`id`),
  KEY `fk_jogo_modalidade` (`id_modalidade`),
  KEY `fk_jogo_timeA` (`id_timeA`),
  KEY `fk_jogo_timeB` (`id_timeB`),
  KEY `fk_jogo_vencedor` (`id_time_vencedor`),
  CONSTRAINT `fk_jogo_modalidade` FOREIGN KEY (`id_modalidade`) REFERENCES `modalidade` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jogo_timeA` FOREIGN KEY (`id_timeA`) REFERENCES `time` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jogo_timeB` FOREIGN KEY (`id_timeB`) REFERENCES `time` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_jogo_vencedor` FOREIGN KEY (`id_time_vencedor`) REFERENCES `time` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabela de Placar e Pontuação Gerada no Jogo
CREATE TABLE `placar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_jogo` INT(11) NOT NULL,
  `gols_pts_a` INT(11) NOT NULL DEFAULT 0,
  `gols_pts_b` INT(11) NOT NULL DEFAULT 0,
  `pontos_sala_a` INT(11) NOT NULL DEFAULT 0,
  `pontos_sala_b` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_placar_jogo` (`id_jogo`),
  CONSTRAINT `fk_placar_jogo` FOREIGN KEY (`id_jogo`) REFERENCES `jogo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados Iniciais
INSERT INTO `sala` (`id`, `nome`) VALUES
(1, 'Diretoria'),
(2, '9° ano'),
(3, '1° EM'),
(4, '2° EM'),
(5, '3° EM');

-- ADM Padrão (RM: 0 | Senha: já configurada com hash)
INSERT INTO `usuario` (`id`, `nome`, `rm`, `senha`, `tipo`, `id_sala`) VALUES
(1, 'Gabriel Machado', '0', '$2y$10$LYpLIp/o9T.EtlCbQv0QXOcXKQMdJ2Wpy0pTf52jHfdcernrF6BQa', 'admin', 1);

COMMIT;
