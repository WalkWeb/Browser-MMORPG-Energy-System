-- Создаем таблицу

CREATE TABLE `energy` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uname` varchar(255) NOT NULL,
  `energy` INT(11) DEFAULT '100',
  `energy_max` INT(11) DEFAULT '100',
  `time` decimal(20,4) NOT NULL,
  `residue` INT(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- И наполняем её

INSERT INTO `energy` (`id`, `uname`, `energy`, `energy_max`, `time`, `residue`) VALUES
  (1, 'Andariel', 94, 120, '1517230970.1444', 12);