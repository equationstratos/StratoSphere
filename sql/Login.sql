CREATE TABLE IF NOT EXISTS `Accounts` (
	`Id` int(11) NOT NULL AUTO_INCREMENT,
  	`Username` varchar(50) NOT NULL,
  	`Password` varchar(255) NOT NULL,
  	`Email` varchar(100) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

INSERT INTO `Accounts` (`Id`, `Username`, `Password`, `Email`) VALUES (1, 'test', '$2y$10$SfhYIDtn.iOuCW7zfoFLuuZHX6lja4lF4XA4JqNmpiH/.P3zB8JCa', 'test@test.com');
