    CREATE TABLE IF NOT EXISTS `Devices` (
      `Id` int(11) NULL AUTO_INCREMENT,
      `BrandName` varchar(20) NULL,
      `ModelName` varchar(20) NULL,
      `ModelOs` varchar(20) NULL,
      `BatteryLevel` varchar(20) NULL,
      `ConnectType` varchar(20) NULL,
      `BoardHardware` varchar(20) NULL,
      `Connected` varchar(20) NULL,
      `Flash` varchar(20) NULL,
      `Vibrate` varchar(20) NULL,
      `Ring` varchar(20) NULL,
      `PlayAudio` varchar(20) NULL,
      `Strobo` varchar(20) NULL,
      `Morse` varchar(20) NULL,
      `RecordVideoFront` varchar(20) NULL,
      `RecordVideoBack` varchar(20) NULL,
      `PictureFrontCamera` varchar(20) NULL,
      `PictureBackCamera` varchar(20) NULL,
      `Localisation` varchar(20) NULL,
      `Latitude` varchar(20) NULL,
      `Longitude` varchar(20) NULL,
      `StreamFront` varchar(20) NULL,
      `StreamBack` varchar(20) NULL,
      `Text2Speach` varchar(20) NULL,

      
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=0 ;