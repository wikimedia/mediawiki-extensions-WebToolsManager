CREATE TABLE IF NOT EXISTS /*_*/webtools_config (
  `wtc_key` varchar(64) NOT NULL PRIMARY KEY,
  `wtc_value` varchar(255) DEFAULT NULL
)/*$wgDBTableOptions*/;
