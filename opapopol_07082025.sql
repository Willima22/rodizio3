-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 07/08/2025 às 18:50
-- Versão do servidor: 10.6.23-MariaDB
-- Versão do PHP: 8.3.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `opapopol_07082025`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`opapopol`@`localhost` PROCEDURE `ResetRodizio` ()   BEGIN
    UPDATE profissionais 
    SET ordem_chegada = 0, 
        total_atendimentos_dia = 0,
        status = 'ausente';
        
    DELETE FROM sessoes_nfc WHERE expires_at < NOW();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `atendimentos`
--

CREATE TABLE `atendimentos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `id_cliente` char(36) NOT NULL,
  `id_profissional` char(36) NOT NULL,
  `id_servico` char(36) NOT NULL,
  `hora_inicio` timestamp NULL DEFAULT NULL,
  `hora_fim` timestamp NULL DEFAULT NULL,
  `status` enum('aguardando','em_andamento','finalizado','cancelado') DEFAULT 'aguardando',
  `valor_cobrado` decimal(10,2) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` bigint(20) NOT NULL,
  `tipo` enum('login','logout','atendimento','erro','sistema') NOT NULL,
  `usuario_id` char(36) DEFAULT NULL,
  `descricao` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `perfis`
--

CREATE TABLE `perfis` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(50) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `perfis`
--

INSERT INTO `perfis` (`id`, `nome`, `descricao`, `criado_em`) VALUES
('6bd8da24-73be-11f0-94dd-92a2db9434cd', 'administrador', 'Acesso total ao sistema', '2025-08-07 18:43:33'),
('6bd8ed1b-73be-11f0-94dd-92a2db9434cd', 'recepcao', 'Cadastro de clientes e gestão da fila', '2025-08-07 18:43:33'),
('6bd8ee9e-73be-11f0-94dd-92a2db9434cd', 'profissional', 'Autenticação e gestão de atendimentos', '2025-08-07 18:43:33'),
('6bd8ef1a-73be-11f0-94dd-92a2db9434cd', 'gestor', 'Visualização de dashboards e relatórios', '2025-08-07 18:43:33');

-- --------------------------------------------------------

--
-- Estrutura para tabela `profissionais`
--

CREATE TABLE `profissionais` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(255) NOT NULL,
  `nfc_uid` varchar(255) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `status` enum('livre','atendendo','ausente') DEFAULT 'ausente',
  `ordem_chegada` int(11) DEFAULT 0,
  `total_atendimentos_dia` int(11) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `profissionais`
--

INSERT INTO `profissionais` (`id`, `nome`, `nfc_uid`, `senha`, `status`, `ordem_chegada`, `total_atendimentos_dia`, `ativo`, `criado_em`, `atualizado_em`) VALUES
('6c612ec7-73be-11f0-94dd-92a2db9434cd', 'Maria Silva', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ausente', 0, 0, 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c6139e3-73be-11f0-94dd-92a2db9434cd', 'Ana Costa', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ausente', 0, 0, 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c613b08-73be-11f0-94dd-92a2db9434cd', 'Carla Santos', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ausente', 0, 0, 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `servicos`
--

CREATE TABLE `servicos` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(255) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `tempo_estimado` time NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `servicos`
--

INSERT INTO `servicos` (`id`, `nome`, `preco`, `tempo_estimado`, `ativo`, `criado_em`, `atualizado_em`) VALUES
('6c62ab2c-73be-11f0-94dd-92a2db9434cd', 'Escova Simples', 25.00, '00:30:00', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c62b67d-73be-11f0-94dd-92a2db9434cd', 'Escova com Prancha', 35.00, '00:45:00', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c62b787-73be-11f0-94dd-92a2db9434cd', 'Escova Modelada', 40.00, '01:00:00', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c62b7d1-73be-11f0-94dd-92a2db9434cd', 'Hidratação', 50.00, '01:30:00', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c62b810-73be-11f0-94dd-92a2db9434cd', 'Corte Feminino', 45.00, '00:45:00', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34');

-- --------------------------------------------------------

--
-- Estrutura para tabela `sessoes_nfc`
--

CREATE TABLE `sessoes_nfc` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `id_profissional` char(36) NOT NULL,
  `nfc_uid` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ativa` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Acionadores `sessoes_nfc`
--
DELIMITER $$
CREATE TRIGGER `cleanup_expired_nfc_sessions` AFTER INSERT ON `sessoes_nfc` FOR EACH ROW BEGIN
    DELETE FROM sessoes_nfc WHERE expires_at < NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `nome` varchar(255) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `id_perfil` char(36) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `usuario`, `senha`, `id_perfil`, `ativo`, `criado_em`, `atualizado_em`) VALUES
('0a6fdc66-73bf-11f0-94dd-92a2db9434cd', 'Willyma', 'Willyma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '6bd8da24-73be-11f0-94dd-92a2db9434cd', 1, '2025-08-07 18:47:59', '2025-08-07 18:47:59'),
('6c5d764c-73be-11f0-94dd-92a2db9434cd', 'Administrador', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '6bd8da24-73be-11f0-94dd-92a2db9434cd', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34'),
('6c5f83ec-73be-11f0-94dd-92a2db9434cd', 'Recepção', 'recepcao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '6bd8ed1b-73be-11f0-94dd-92a2db9434cd', 1, '2025-08-07 18:43:34', '2025-08-07 18:43:34');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_servico` (`id_servico`),
  ADD KEY `idx_atendimentos_data` (`criado_em`),
  ADD KEY `idx_atendimentos_status` (`status`),
  ADD KEY `idx_atendimentos_profissional` (`id_profissional`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `perfis`
--
ALTER TABLE `perfis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `profissionais`
--
ALTER TABLE `profissionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nfc_uid` (`nfc_uid`),
  ADD KEY `idx_profissionais_nfc` (`nfc_uid`),
  ADD KEY `idx_profissionais_status` (`status`),
  ADD KEY `idx_profissionais_ordem` (`ordem_chegada`);

--
-- Índices de tabela `servicos`
--
ALTER TABLE `servicos`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `sessoes_nfc`
--
ALTER TABLE `sessoes_nfc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_profissional` (`id_profissional`),
  ADD KEY `idx_sessoes_nfc_expires` (`expires_at`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_perfil` (`id_perfil`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `atendimentos`
--
ALTER TABLE `atendimentos`
  ADD CONSTRAINT `atendimentos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `atendimentos_ibfk_2` FOREIGN KEY (`id_profissional`) REFERENCES `profissionais` (`id`),
  ADD CONSTRAINT `atendimentos_ibfk_3` FOREIGN KEY (`id_servico`) REFERENCES `servicos` (`id`);

--
-- Restrições para tabelas `sessoes_nfc`
--
ALTER TABLE `sessoes_nfc`
  ADD CONSTRAINT `sessoes_nfc_ibfk_1` FOREIGN KEY (`id_profissional`) REFERENCES `profissionais` (`id`);

--
-- Restrições para tabelas `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_perfil`) REFERENCES `perfis` (`id`);

DELIMITER $$
--
-- Eventos
--
CREATE DEFINER=`opapopol`@`localhost` EVENT `reset_diario` ON SCHEDULE EVERY 1 DAY STARTS '2025-08-08 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL ResetRodizio()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
