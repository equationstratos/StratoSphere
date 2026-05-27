CREATE TABLE IF NOT EXISTS `Commands` (
	`id` int(11) NULL,
	`IdCommand` int(11) NULL,
  	`Command` varchar(50) NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `Commands` ( `id`, `IdCommand`, `Command`) VALUES (1, 1, 'okok');
